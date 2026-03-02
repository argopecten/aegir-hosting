<?php

declare(strict_types=1);

namespace Drupal\hosting_platform\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\hosting\Service\ContextRegistry;
use Drupal\hosting_platform\Entity\HostingPlatform;
use Drupal\hosting_task\Service\TaskManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Entity lifecycle hooks for hosting_platform entities.
 *
 * Manages synchronization between platform entities and backend
 * provision contexts (YAML alias files).
 */
class PlatformLifecycleHooks {

  protected readonly LoggerInterface $logger;

  public function __construct(
    protected readonly ContextRegistry $contextRegistry,
    LoggerChannelFactoryInterface $loggerFactory,
    protected readonly ?TaskManagerInterface $taskManager = NULL,
  ) {
    $this->logger = $loggerFactory->get('hosting');
  }

  /**
   * React to platform entity creation.
   *
   * Saves the backend context and queues a verify task.
   */
  #[Hook('hosting_platform_insert')]
  public function onInsert(HostingPlatform $platform): void {
    try {
      $contextName = $this->contextRegistry->saveContextToBackend($platform);
      $this->taskManager?->createTask($contextName, 'verify', [], []);
      \Drupal::messenger()->addStatus(t('Platform verification task has been queued.'));
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to save context for new platform @name: @message', [
        '@name' => $platform->get('name')->value,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * React to platform entity updates.
   *
   * Re-syncs the backend context if publish_path changed.
   */
  #[Hook('hosting_platform_update')]
  public function onUpdate(HostingPlatform $platform): void {
    $pathChanged = $platform->get('publish_path')->value !== $platform->original->get('publish_path')->value;
    if (!$pathChanged) {
      return;
    }

    try {
      $contextName = $this->contextRegistry->saveContextToBackend($platform);
      $this->taskManager?->createTask($contextName, 'verify', [], []);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to update context for platform @name: @message', [
        '@name' => $platform->get('name')->value,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Clean up backend context after platform deletion.
   */
  #[Hook('hosting_platform_delete')]
  public function onDelete(HostingPlatform $platform): void {
    $contextName = $this->contextRegistry->resolveContextName($platform);
    $this->contextRegistry->deleteContextFromBackend(
      $contextName,
      'hosting_platform',
      (int) $platform->id()
    );
  }

}
