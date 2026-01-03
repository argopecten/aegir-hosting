<?php

declare(ticks = 1);

namespace Drupal\hosting_queued\Commands;

use Drush\Attributes as Drush;
use Drush\Commands\DrushCommands;
use Drush\Boot\DrupalBootLevels;
use Drupal\Core\Database\Database;

final class HostingQueuedCommands extends DrushCommands {
  /**
   * Run the hosting task queue daemon.
   */
  #[Drush\Command(name: 'hosting:queued', aliases: ['hosting-queued'])]
  #[Drush\Option(name: 'onetime', description: 'Exit after processing available tasks.')]
  #[Drush\Bootstrap(level: DrupalBootLevels::FULL)]
  public function queued(array $options = ['onetime' => FALSE]): void {
    if (function_exists('pcntl_signal')) {
      pcntl_signal(SIGHUP, [$this, 'handleRestart']);
      pcntl_signal(SIGINT, [$this, 'handleStop']);
      pcntl_signal(SIGTERM, [$this, 'handleStop']);
    }

    if (function_exists('set_time_limit')) {
      @set_time_limit(0);
    }

    if (strpos((string) ini_get('variables_order'), 'E') === FALSE) {
      if (strpos((string) ini_get('variables_order'), 'S') === FALSE) {
        $this->logger()->warning('Neither $_ENV nor $_SERVER are available to set up proper environment inheritance; ensure E and/or S is set in your php.ini variables_order setting.');
      }
      else {
        $_ENV = $_SERVER;
      }
    }

    $config_factory = \Drupal::configFactory();
    $config = $config_factory->get('hosting_queued.settings');
    $end_time = ($config->get('hosting_queued_process_lifetime') ?? 3600) + REQUEST_TIME;

    \Drupal::state()->set('hosting_queued_process_started', REQUEST_TIME);

    $is_paused = $config->get('hosting_queued_paused') ?? 0;
    if ($is_paused) {
      \Drupal::logger('hosting_queued')->notice('Started Hosting queue daemon, hosting queue is paused.');
      $this->logger()->notice('Started hosting queue daemon, hosting queue is paused.');
    }
    else {
      \Drupal::logger('hosting_queued')->notice('Started Hosting queue daemon, waiting for new tasks');
      $this->logger()->notice('Started hosting queue daemon. Waiting for new tasks.');
    }

    while (TRUE) {
      $config_factory->reset('hosting_queued.settings');
      $config = $config_factory->get('hosting_queued.settings');
      if ($config->get('hosting_queued_paused') ?? 0) {
        if (!$is_paused) {
          $this->logger()->notice('Hosting Queued has been paused.');
        }
        sleep(1);
        $is_paused = $config->get('hosting_queued_paused') ?? 0;
        continue;
      }

      if ($is_paused) {
        $this->logger()->notice('Hosting Queued has been unpaused.');
      }
      $is_paused = $config->get('hosting_queued_paused') ?? 0;

      try {
        if (time() > $end_time) {
          $this->handleRestart();
        }

        if ($tasks = @hosting_get_new_tasks(1)) {
          $this->logger()->notice('Found @count tasks in queue. Running...', ['@count' => count($tasks)]);

          if (lock_acquire('hosting_queue_tasks_running', HOSTING_QUEUE_LOCK_TIMEOUT)) {
            $this->logger()->notice('Acquired lock on task queue.');
            foreach ($tasks as $task) {
              $this->logger()->notice('Found task to execute. Pausing before execution.');
              sleep(1);

              hosting_task_execute($task, ['interactive' => TRUE]);

              $delay = $config->get('hosting_queued_post_task_delay') ?? 0;
              if (!empty($delay)) {
                $this->logger()->notice('Going to sleep for @count seconds after completing task.', ['@count' => $delay]);
                sleep($delay);
              }

              unset($task);
              if (REQUEST_TIME > $end_time) {
                $this->handleRestart();
              }
            }
            $this->logger()->notice('Releasing lock on task queue.');
            lock_release('hosting_queue_tasks_running');
          }
        }
      }
      catch (\Exception $e) {
        $this->logger()->warning('Caught database error.');
        $this->logger()->warning('Waiting for database to become available.');
        $timeout = 120;
        do {
          sleep(1);
          $timeout--;
          $connect = FALSE;
          try {
            $connect = Database::getConnection();
          }
          catch (\Exception $e) {
            $connect = FALSE;
          }
          $this->logger()->warning('.');
        } while (!$connect && $timeout > 0);
        Database::closeConnection();
        $this->logger()->warning('Restarting queue daemon.');
        $this->handleRestart();
      }

      sleep(1);

      if (!empty($options['onetime'])) {
        $this->logger()->notice('Exiting after processing all tasks, as requested by --onetime.');
        break;
      }
      unset($tasks);
    }
  }

  /**
   * Release stale locks on the task queue.
   */
  #[Drush\Command(name: 'hosting:release-lock', aliases: ['hosting-release-lock'])]
  #[Drush\Bootstrap(level: DrupalBootLevels::FULL)]
  public function releaseLock(): void {
    $this->logger()->notice('Clearing any stale locks on task queue.');
    $name = 'hosting_queue_tasks_running';
    global $locks;
    unset($locks[$name]);
    \Drupal::database()->delete('semaphore')
      ->condition('name', $name)
      ->execute();
  }

  /**
   * Handle interruption signals gracefully.
   */
  public function handleStop(int $signal): void {
    \Drupal::logger('hosting_queued')->notice('Received signal @signal, waiting for children to die.', ['@signal' => $signal]);
    $status = NULL;
    pcntl_wait($status);
    $this->logger()->notice('Releasing lock on task queue.');
    lock_release('hosting_queue_tasks_running');
    \Drupal::logger('hosting_queued')->notice('Stopped daemon');
    exit($status);
  }

  /**
   * Restart the dispatcher to work around memory leaks.
   */
  public function handleRestart(int $signal = NULL): void {
    try {
      if ($signal !== NULL) {
        \Drupal::logger('hosting_queued')->notice('Received signal @signal, waiting for children to die.', ['@signal' => $signal]);
        $status = NULL;
        pcntl_wait($status);
      }

      if (function_exists('pcntl_exec')) {
        $args = $_ENV['argv'] ?? $_SERVER['argv'] ?? [];
        $drush = array_shift($args);
        unset($_ENV['argv']);

        \Drupal::logger('hosting_queued')->notice('Restarting queue daemon with @drush @args.', [
          '@drush' => $drush,
          '@args' => implode(' ', $args),
        ]);
        $this->logger()->notice('Releasing lock on task queue.');
        lock_release('hosting_queue_tasks_running');
        $this->closeAllConnections();
      }
      else {
        \Drupal::logger('hosting_queued')->warning('PCNTL not installed, unable to auto-restart.');
      }
    }
    catch (\Exception $e) {
      // Ignore and attempt restart below.
    }

    try {
      if (function_exists('pcntl_exec')) {
        pcntl_exec($drush, $args, $_ENV);
        $this->logger()->error('Could not restart the queue daemon, aborting.');
      }
    }
    catch (\Exception $e) {
      // Ignore failures.
    }

    $this->logger()->notice('Releasing lock on task queue.');
    lock_release('hosting_queue_tasks_running');
    exit(1);
  }

  /**
   * Close all database file descriptors, as exec() does not close them.
   */
  private function closeAllConnections(): void {
    global $databases;
    foreach (array_keys($databases) as $target) {
      foreach (array_keys($databases[$target]) as $key) {
        Database::closeConnection($target, $key);
      }
    }
  }

}
