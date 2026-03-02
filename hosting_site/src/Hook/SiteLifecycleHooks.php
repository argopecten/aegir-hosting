<?php

declare(strict_types=1);

namespace Drupal\hosting_site\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\hosting\Service\ContextRegistry;
use Drupal\hosting_site\Entity\HostingSite;
use Drupal\hosting_task\Service\TaskManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Entity lifecycle hooks for hosting_site entities.
 *
 * Manages synchronization between site entities and backend
 * provision contexts (YAML alias files).
 */
class SiteLifecycleHooks {

  protected readonly LoggerInterface $logger;

  public function __construct(
    protected readonly ContextRegistry $contextRegistry,
    LoggerChannelFactoryInterface $loggerFactory,
    protected readonly ?TaskManagerInterface $taskManager = NULL,
  ) {
    $this->logger = $loggerFactory->get('hosting');
  }

  /**
   * React to site entity creation.
   *
   * Saves the backend context and queues an install task.
   */
  #[Hook('hosting_site_insert')]
  public function onInsert(HostingSite $site): void {
    try {
      $contextName = $this->contextRegistry->saveContextToBackend($site);
      $this->taskManager?->createTask($contextName, 'install', [], []);
      \Drupal::messenger()->addStatus(t('Site installation task has been queued.'));
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to save context for new site @domain: @message', [
        '@domain' => $site->get('domain')->value,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * React to site entity updates.
   *
   * Re-syncs the backend context and queues a verify task when
   * the site transitions from unpublished to published.
   */
  #[Hook('hosting_site_update')]
  public function onUpdate(HostingSite $site): void {
    // Re-sync context data on every update.
    try {
      $contextName = $this->contextRegistry->saveContextToBackend($site);

      // Queue verify task when site transitions to enabled.
      $currentStatus = (int) $site->get('status')->value;
      $originalStatus = (int) $site->original->get('status')->value;
      if ($currentStatus === HostingSite::STATUS_ENABLED && $originalStatus !== HostingSite::STATUS_ENABLED) {
        $this->taskManager?->createTask($contextName, 'verify', [], []);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to update context for site @domain: @message', [
        '@domain' => $site->get('domain')->value,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Clean up backend context after site deletion.
   */
  #[Hook('hosting_site_delete')]
  public function onDelete(HostingSite $site): void {
    $contextName = $this->contextRegistry->resolveContextName($site);
    $this->contextRegistry->deleteContextFromBackend(
      $contextName,
      'hosting_site',
      (int) $site->id()
    );
  }

}
