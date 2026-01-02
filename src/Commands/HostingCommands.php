<?php

namespace Drupal\hosting\Commands;

use Drush\Attributes as Drush;
use Drush\Commands\DrushCommands;
use Drush\Drupal\DrupalBootLevels;
use Drush\Exceptions\UserAbortException;
use Drupal\Core\Entity\ContentEntityInterface;

final class HostingCommands extends DrushCommands {
  /**
   * Dispatch all hosting queues.
   */
  #[Drush\Command(name: 'hosting:dispatch', aliases: ['hosting-dispatch'])]
  #[Drush\Option(name: 'lock-wait', description: 'Time to wait to acquire a lock on dispatched queues.')]
  #[Drush\Option(name: 'force', description: 'Continue even if the task queue is still locked.')]
  #[Drush\Bootstrap(level: DrupalBootLevels::FULL)]
  public function dispatch(array $options = ['lock-wait' => HOSTING_QUEUE_DEFAULT_LOCK_WAIT, 'force' => FALSE]): void {
    $now = \Drupal::time()->getRequestTime();
    \Drupal::state()->set('hosting_dispatch_last_run', $now);
    $this->logger()->notice('Dispatching queues.');

    if (!\Drupal::state()->get('hosting_dispatch_enabled', FALSE)) {
      $this->logger()->notice('Dispatching disabled.');
      return;
    }

    $queues = hosting_get_queues();
    foreach ($queues as $queue => $info) {
      $semaphore = "hosting_dispatch_{$queue}_running";
      $this->acquireLock($queue, $semaphore, (int) $options['lock-wait'], (bool) $options['force']);
      try {
        if (!empty($info['enabled'])) {
          if (($now - $info['last']) >= $info['calc_frequency'] || $options['force']) {
            $count = $info['calc_items'] - $info['running_items'];
            if ($count <= 0) {
              $this->logger()->notice('Maximum number of tasks (@count) already running.', ['@count' => $info['running_items']]);
            }
            else {
              $this->logger()->notice('Found @running running tasks, starting @count out of @max items.', [
                '@running' => $info['running_items'],
                '@count' => $count,
                '@max' => $info['calc_items'],
              ]);
              $this->runQueue($queue, $count, (int) $options['lock-wait'], (bool) $options['force']);
            }
          }
          else {
            $this->logger()->notice('Too early for queue @queue.', ['@queue' => $queue]);
          }
        }
        else {
          $this->logger()->notice('Queue @queue disabled.', ['@queue' => $queue]);
        }
      }
      finally {
        $this->logger()->notice('Releasing @queue lock.', ['@queue' => $queue]);
        lock_release($semaphore);
      }
    }
  }

  /**
   * Run a specific hosting queue.
   */
  #[Drush\Command(name: 'hosting:queue', aliases: ['hosting-queue'])]
  #[Drush\Argument(name: 'queue', description: 'Queue name to run.')]
  #[Drush\Option(name: 'items', description: 'Number of items to process.')]
  #[Drush\Option(name: 'lock-wait', description: 'Time to wait to acquire a lock on the queue.')]
  #[Drush\Option(name: 'force', description: 'Continue even if the queue is still locked.')]
  #[Drush\Bootstrap(level: DrupalBootLevels::FULL)]
  public function queue(string $queue, array $options = ['items' => 5, 'lock-wait' => HOSTING_QUEUE_DEFAULT_LOCK_WAIT, 'force' => FALSE]): void {
    $this->runQueue($queue, (int) $options['items'], (int) $options['lock-wait'], (bool) $options['force']);
  }

  /**
   * Execute a specific queue item/task.
   */
  #[Drush\Command(name: 'hosting:task', aliases: ['hosting-task'])]
  #[Drush\Argument(name: 'context_name', description: 'Context to work on or a task ID.')]
  #[Drush\Argument(name: 'command', description: 'Provision command to invoke.')]
  #[Drush\Argument(name: 'task_args', description: 'Additional task arguments in name=value format.', multiple: TRUE)]
  #[Drush\Option(name: 'force', description: 'Force the specified task to execute even if it is not queued to run.')]
  #[Drush\Bootstrap(level: DrupalBootLevels::FULL)]
  public function task(string $context_name, ?string $command = NULL, array $task_args = [], array $options = ['force' => FALSE]): void {
    require_once dirname(__DIR__, 2) . '/task.hosting.inc';

    $arguments = [$context_name];
    if ($command !== NULL) {
      $arguments[] = $command;
    }
    $arguments = array_merge($arguments, $task_args);

    drush_hosting_task_validate(...$arguments);
    if (function_exists('drush_get_error') && drush_get_error()) {
      throw new UserAbortException('Task validation failed.');
    }

    try {
      drush_hosting_task();
      if (function_exists('drush_get_error') && drush_get_error()) {
        drush_hosting_task_rollback();
        throw new UserAbortException('Task execution failed.');
      }
      drush_hosting_post_hosting_task(NULL);
    }
    catch (\Exception $e) {
      if (function_exists('drush_hosting_task_rollback')) {
        drush_hosting_task_rollback();
      }
      throw $e;
    }
  }

  /**
   * Import an existing backend context name into the frontend.
   */
  #[Drush\Command(name: 'hosting:import', aliases: ['hosting-import'])]
  #[Drush\Argument(name: 'context_name', description: 'Context to import.')]
  #[Drush\Bootstrap(level: DrupalBootLevels::FULL)]
  public function import(string $context_name): void {
    if ($context_name === '') {
      throw new UserAbortException('Context name is required.');
    }

    if (function_exists('d')) {
      $context = d($context_name);
      if (!$context || empty($context->name)) {
        throw new UserAbortException("Context {$context_name} could not be resolved.");
      }
    }

    $this->logger()->notice('Importing @context.', ['@context' => $context_name]);
    $this->importContext($context_name);
  }

  /**
   * Pause the hostmaster queues.
   */
  #[Drush\Command(name: 'hosting:pause', aliases: ['hosting-pause'])]
  #[Drush\Bootstrap(level: DrupalBootLevels::FULL)]
  public function pause(): void {
    while (hosting_task_count_running() > 0) {
      $this->logger()->notice('Waiting for the task queue to be processed and tasks to complete.');
      sleep(1);
    }

    if (function_exists('_hosting_setup_cron')) {
      _hosting_setup_cron(FALSE);
    }
    else {
      \Drupal::state()->set('hosting_dispatch_enabled', FALSE);
      $this->logger()->notice('Hosting dispatch disabled.');
    }
  }

  /**
   * Resume the hostmaster site after migration.
   */
  #[Drush\Command(name: 'hosting:resume', aliases: ['hosting-resume'])]
  #[Drush\Option(name: 'old_platform_name', description: 'The old platform name.')]
  #[Drush\Option(name: 'new_platform_name', description: 'The new platform name.')]
  #[Drush\Bootstrap(level: DrupalBootLevels::FULL)]
  public function resume(array $options = ['old_platform_name' => NULL, 'new_platform_name' => NULL]): void {
    $this->logger()->notice('Bootstrapping Drupal.');

    $old_platform = $options['old_platform_name'];
    $new_platform = $options['new_platform_name'];

    $site = function_exists('d') ? hosting_context_load(d()->name) : NULL;
    if (!$site instanceof ContentEntityInterface) {
      $this->logger()->error('Unable to load hostmaster site entity from context.');
      return;
    }

    $old_platform_root = $old_platform ? d($old_platform)->root : NULL;
    if ($old_platform_root) {
      $platform_id = \Drupal::database()->query('SELECT id FROM {hosting_platform} WHERE publish_path = :publish_path AND status = :status', [
        ':publish_path' => $old_platform_root,
        ':status' => HOSTING_PLATFORM_ENABLED,
      ])->fetchField();
      if ($platform_id) {
        hosting_context_register($platform_id, ltrim($old_platform, '@'));
      }
      else {
        $this->logger()->warning('Old platform not found in path @path, not setting context @context', [
          '@path' => $old_platform_root,
          '@context' => ltrim((string) $old_platform, '@'),
        ]);
      }
    }

    $platform_root = function_exists('d') ? d()->root : NULL;
    $platform_id = \Drupal::database()->query('SELECT id FROM {hosting_platform} WHERE publish_path = :publish_path', [
      ':publish_path' => $platform_root,
    ])->fetchField();

    if (!$platform_id) {
      $this->logger()->notice('Platform not found for path @path, adding platform entity', ['@path' => $platform_root]);
      $platform_source_id = $site->get('platform')->target_id;
      $platform_source = $platform_source_id ? \Drupal::entityTypeManager()->getStorage('hosting_platform')->load($platform_source_id) : NULL;
      $values = [
        'label' => basename(rtrim((string) $platform_root, '/')),
        'publish_path' => $platform_root,
        'verified' => 0,
        'status' => $platform_source ? hosting_entity_status($platform_source, 'platform') : HOSTING_PLATFORM_ENABLED,
      ];
      if ($platform_source) {
        $values['web_server'] = $platform_source->get('web_server')->target_id;
      }
      $platform = \Drupal::entityTypeManager()->getStorage('hosting_platform')->create($values);
      $platform->save();
      $platform_id = $platform->id();
    }
    else {
      $this->logger()->notice('Platform found for path @path: #@id', ['@path' => $platform_root, '@id' => $platform_id]);
    }

    if ($new_platform) {
      hosting_context_register($platform_id, ltrim($new_platform, '@'));
    }

    $this->logger()->notice('Fixing platform for site.');
    $site->set('verified', 0);
    $site->set('platform', $platform_id);
    $site->save();
    provision_backend_invoke(d()->name, 'hosting-setup');
  }

  /**
   * Set up initial configuration settings.
   */
  #[Drush\Command(name: 'hosting:setup', aliases: ['hosting-setup'])]
  #[Drush\Bootstrap(level: DrupalBootLevels::FULL)]
  public function setup(): void {
    if (function_exists('_hosting_setup_cron')) {
      _hosting_setup_cron(TRUE);
      return;
    }

    if (function_exists('hosting_queues_cron_cmd')) {
      $this->logger()->notice('Add the following entry to your crontab:');
      $this->io()->writeln(hosting_queues_cron_cmd());
    }
  }

  /**
   * Run a queue with locking.
   */
  private function runQueue(string $queue, int $count, int $lock_wait, bool $force): void {
    if ($count < 0) {
      $count = 0;
    }

    \Drupal::state()->set('hosting_queue_' . $queue . '_last_run', \Drupal::time()->getRequestTime());

    $semaphore = "hosting_queue_{$queue}_running";
    $this->logger()->notice('Acquiring lock on @queue queue.', ['@queue' => $queue]);
    $this->acquireLock($queue, $semaphore, $lock_wait, $force);

    try {
      $func = 'hosting_' . $queue . '_queue';
      if (function_exists($func)) {
        $func($count);
      }
    }
    finally {
      $this->logger()->notice('Releasing @queue lock.', ['@queue' => $queue]);
      lock_release($semaphore);
    }
  }

  /**
   * Acquire a lock for queue processing.
   */
  private function acquireLock(string $queue, string $semaphore, int $lock_wait, bool $force): void {
    if (!lock_wait($semaphore, $lock_wait) || $force) {
      if (lock_acquire($semaphore, HOSTING_QUEUE_LOCK_TIMEOUT)) {
        $this->logger()->notice('Acquired lock on @queue queue.', ['@queue' => $queue]);
      }
      elseif ($force) {
        $this->logger()->warning('Bypassing lock on @queue queue.', ['@queue' => $queue]);
      }
      else {
        throw new UserAbortException("Cannot acquire lock on {$queue} queue.");
      }
    }
    elseif ($force) {
      $this->logger()->warning('Bypassing lock on @queue queue.', ['@queue' => $queue]);
    }
    else {
      throw new UserAbortException("Cannot acquire lock on {$queue} queue after waiting {$lock_wait} seconds.");
    }
  }

  /**
   * Import a backend context into the frontend.
   */
  private function importContext($alias): ?int {
    $name = is_object($alias) ? $alias->name : $alias;
    static $known_contexts = [];

    if (isset($known_contexts[$name])) {
      $this->logger()->notice('Already re-imported @name in this process.', ['@name' => $name]);
      return $known_contexts[$name];
    }

    $context = d($name);
    $entity_type_id = hosting_entity_type_from_task_scope($context->type);
    if (!$entity_type_id) {
      $this->logger()->error('Unknown context type @type for @name.', ['@type' => $context->type, '@name' => $name]);
      return NULL;
    }

    $node = hosting_context_load($name);
    if ($node instanceof ContentEntityInterface) {
      $this->logger()->notice('Context @name already has an associated entity. Updating @type @id instead.', [
        '@name' => $name,
        '@type' => $node->getEntityTypeId(),
        '@id' => $node->id(),
      ]);
      $node->no_verify = TRUE;
      $known_contexts[$name] = $node->id();
    }
    else {
      $node = \Drupal::entityTypeManager()->getStorage($entity_type_id)->create(['status' => 1]);
      $hosting_name = trim($context->name, '@');
      if ($node->hasField('hosting_name')) {
        $node->set('hosting_name', $hosting_name);
      }
      else {
        $node->hosting_name = $hosting_name;
      }
    }

    \Drupal::moduleHandler()->invokeAll('drush_context_import', [$context, $node]);

    if ($node->hasField('label') && !$node->label()) {
      $node->set('label', trim($context->name, '@'));
    }
    $node->save();
    hosting_context_register($node->id(), $node->hasField('hosting_name') ? $node->get('hosting_name')->value : trim($context->name, '@'));
    $this->logger()->notice('Context @name has been imported. Updated @type @id.', [
      '@name' => $name,
      '@type' => $node->getEntityTypeId(),
      '@id' => $node->id(),
    ]);
    $known_contexts[$name] = $node->id();

    return $node->id();
  }

}
