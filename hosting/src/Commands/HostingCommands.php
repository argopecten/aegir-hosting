<?php

namespace Drupal\hosting\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\hosting\Service\ContextRegistry;
use Drupal\hosting\Service\QueueDispatcher;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

class HostingCommands extends DrushCommands {

  protected QueueDispatcher $dispatcher;
  protected ConfigFactoryInterface $configFactory;
  protected ContextRegistry $contextRegistry;

  public function __construct(QueueDispatcher $dispatcher, ConfigFactoryInterface $configFactory, ContextRegistry $contextRegistry) {
    $this->dispatcher = $dispatcher;
    $this->configFactory = $configFactory;
    $this->contextRegistry = $contextRegistry;
  }

  #[CLI\Command(name: 'hosting:dispatch', aliases: ['hosting-dispatch'])]
  #[CLI\Description('Dispatch Hosting queues based on configured schedules.')]
  public function dispatch(): void {
    $this->dispatcher->dispatch();
    $this->logger()->success('Queues dispatched.');
  }

  #[CLI\Command(name: 'hosting:setup', aliases: ['hosting-setup'])]
  #[CLI\Description('Initialize Hosting dispatch configuration and display recommended cron entry.')]
  public function setup(): void {
    $config = $this->configFactory->getEditable('hosting.settings');
    $config->set('dispatch_enabled', TRUE)->save();
    $this->logger()->success('Hosting dispatch enabled.');
    $this->logger()->notice('Cron should run `drush hosting:dispatch` every minute.');
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

}
