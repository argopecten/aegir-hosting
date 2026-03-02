<?php

namespace Drupal\hosting\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\hosting\Service\BackendInvokerInterface;
use Drupal\hosting\Service\ContextRegistry;
use Drupal\hosting\Service\QueueDispatcher;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

class HostingCommands extends DrushCommands {

  protected QueueDispatcher $dispatcher;
  protected ConfigFactoryInterface $configFactory;
  protected ContextRegistry $contextRegistry;
  protected BackendInvokerInterface $backendInvoker;

  public function __construct(QueueDispatcher $dispatcher, ConfigFactoryInterface $configFactory, ContextRegistry $contextRegistry, BackendInvokerInterface $backendInvoker) {
    $this->dispatcher = $dispatcher;
    $this->configFactory = $configFactory;
    $this->contextRegistry = $contextRegistry;
    $this->backendInvoker = $backendInvoker;
  }

  #[CLI\Command(name: 'hosting:dispatch', aliases: ['hosting-dispatch'])]
  #[CLI\Description('Dispatch Hosting queues based on configured schedules.')]
  public function dispatch(): void {
    $this->dispatcher->dispatch();
    $this->logger()->success('Queues dispatched.');
  }

  #[CLI\Command(name: 'hosting:dispatch-enable', aliases: ['hosting-setup', 'hosting:setup'])]
  #[CLI\Description('Enable Hosting dispatch and install the crontab entry.')]
  public function dispatchEnable(): void {
    $config = $this->configFactory->getEditable('hosting.settings');
    $config->set('dispatch_enabled', TRUE)->save();
    $this->logger()->success('Hosting dispatch enabled.');

    // Install crontab entry via the provision backend.
    $drupalRoot = dirname(\Drupal::root());
    $drushPath = $config->get('backend.drush_path') ?: $drupalRoot . '/vendor/bin/drush';
    $frequency = (string) ($config->get('queues.tasks.frequency') ?: 300);

    $result = $this->backendInvoker->invoke(
      'provision:cron-add',
      [],
      [
        'drupal-root' => $drupalRoot,
        'drush-path' => $drushPath,
        'frequency' => $frequency,
      ]
    );

    if (empty($result['exit_code'])) {
      $this->logger()->success('Crontab entry installed for hosting:dispatch.');
    }
    else {
      $this->logger()->warning('Failed to install crontab entry. Run `drush hosting:cron-add` manually.');
    }
  }

  #[CLI\Command(name: 'hosting:import', aliases: ['hosting-import'])]
  #[CLI\Description('Import a backend context into the frontend registry.')]
  #[CLI\Argument(name: 'context', description: 'Context name, e.g. @example.com')]
  public function import(string $context): void {
    $context = ltrim($context, '@');
    $this->contextRegistry->register($context, 'unknown', 0);
    $this->logger()->success(sprintf('Imported context %s.', $context));
  }

  #[CLI\Command(name: 'hosting:pause', aliases: ['hosting-pause'])]
  #[CLI\Description('Pause Hosting queue dispatch.')]
  public function pause(): void {
    $config = $this->configFactory->getEditable('hosting.settings');
    $config->set('dispatch_enabled', FALSE)->save();
    $this->logger()->success('Hosting dispatch paused.');
  }

  #[CLI\Command(name: 'hosting:resume', aliases: ['hosting-resume'])]
  #[CLI\Description('Resume Hosting queue dispatch.')]
  public function resume(): void {
    $config = $this->configFactory->getEditable('hosting.settings');
    $config->set('dispatch_enabled', TRUE)->save();
    $this->logger()->success('Hosting dispatch resumed.');
  }

  #[CLI\Command(name: 'hosting:cron-add')]
  #[CLI\Description('Add or update the crontab entry for hosting dispatch.')]
  #[CLI\Option(name: 'frequency', description: 'Interval in seconds (default: from queue config)')]
  public function cronAdd(array $options = ['frequency' => '']): void {
    $config = $this->configFactory->get('hosting.settings');
    $drupalRoot = dirname(\Drupal::root());
    $drushPath = $config->get('backend.drush_path') ?: $drupalRoot . '/vendor/bin/drush';
    $frequency = !empty($options['frequency'])
      ? $options['frequency']
      : (string) ($config->get('queues.tasks.frequency') ?: 300);

    $result = $this->backendInvoker->invoke(
      'provision:cron-add',
      [],
      [
        'drupal-root' => $drupalRoot,
        'drush-path' => $drushPath,
        'frequency' => $frequency,
      ]
    );

    if (empty($result['exit_code'])) {
      $this->logger()->success('Crontab entry installed.');
    }
    else {
      $this->logger()->error('Failed to install crontab entry: ' . ($result['error'] ?? 'Unknown error'));
    }
  }

  #[CLI\Command(name: 'hosting:cron-delete')]
  #[CLI\Description('Remove the crontab entry for hosting dispatch.')]
  public function cronDelete(): void {
    $drupalRoot = dirname(\Drupal::root());

    $result = $this->backendInvoker->invoke(
      'provision:cron-delete',
      [],
      ['identifier' => $drupalRoot]
    );

    if (empty($result['exit_code'])) {
      $this->logger()->success('Crontab entry removed.');
    }
    else {
      $this->logger()->error('Failed to remove crontab entry: ' . ($result['error'] ?? 'Unknown error'));
    }
  }

  #[CLI\Command(name: 'hosting:cron-status')]
  #[CLI\Description('Show status of the crontab entry for hosting dispatch.')]
  public function cronStatus(): void {
    $drupalRoot = dirname(\Drupal::root());

    $result = $this->backendInvoker->invoke(
      'provision:cron-status',
      [],
      ['identifier' => $drupalRoot]
    );

    if (!empty($result['output'])) {
      $this->io()->write($result['output']);
    }
    else {
      $this->logger()->notice('No cron status available.');
    }
  }

}
