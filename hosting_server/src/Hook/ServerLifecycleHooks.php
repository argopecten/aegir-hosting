<?php

declare(strict_types=1);

namespace Drupal\hosting_server\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\hosting\Service\ContextRegistry;
use Drupal\hosting_server\Entity\HostingServer;
use Drupal\hosting_task\Service\TaskManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Entity lifecycle hooks for hosting_server entities.
 *
 * Manages the synchronization between server entities and backend
 * provision contexts (YAML alias files).
 */
class ServerLifecycleHooks {

  protected readonly LoggerInterface $logger;

  public function __construct(
    protected readonly ContextRegistry $contextRegistry,
    LoggerChannelFactoryInterface $loggerFactory,
    protected readonly ?TaskManagerInterface $taskManager = NULL,
  ) {
    $this->logger = $loggerFactory->get('hosting');
  }

  /**
   * React to server entity creation.
   *
   * Saves the backend context and queues a verify task.
   * Skipped during module install seeding (handled by
   * _hosting_server_seed_default_servers instead).
   */
  #[Hook('hosting_server_insert')]
  public function onInsert(HostingServer $server): void {
    // During module install, the seed function handles context saves
    // after service instances are created.
    if (ContextRegistry::$seedingInProgress) {
      return;
    }

    try {
      $contextName = $this->contextRegistry->saveContextToBackend($server);
      $this->taskManager?->createTask($contextName, 'verify', [], []);
      $this->logger->info('Queued verify task for new server @context.', [
        '@context' => $contextName,
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to save context for new server @hostname: @message', [
        '@hostname' => $server->get('hostname')->value,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * React to server entity updates.
   *
   * Re-syncs the backend context and queues a verify task.
   */
  #[Hook('hosting_server_update')]
  public function onUpdate(HostingServer $server): void {
    try {
      $contextName = $this->contextRegistry->saveContextToBackend($server);
      $this->taskManager?->createTask($contextName, 'verify', [], []);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to update context for server @hostname: @message', [
        '@hostname' => $server->get('hostname')->value,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * React to server entity deletion.
   *
   * Blocks deletion of server_master. For other servers, removes the
   * backend context and hosting_context record.
   */
  #[Hook('hosting_server_predelete')]
  public function onPreDelete(HostingServer $server): void {
    $contextName = $this->contextRegistry->resolveContextName($server);

    if ($contextName === 'server_master') {
      throw new \RuntimeException('The default server (server_master) cannot be deleted.');
    }
  }

  /**
   * Clean up backend context after server deletion.
   */
  #[Hook('hosting_server_delete')]
  public function onDelete(HostingServer $server): void {
    $contextName = $this->contextRegistry->resolveContextName($server);
    $this->contextRegistry->deleteContextFromBackend(
      $contextName,
      'hosting_server',
      (int) $server->id()
    );
  }

}
