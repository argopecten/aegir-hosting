<?php

namespace Drupal\hosting_task\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\hosting_platform\Entity\HostingPlatform;
use Drupal\hosting_site\Entity\HostingSite;

/**
 * Determines which task actions are available for a given hosting entity.
 *
 * Implements core D7 Aegir logic:
 * - Enabled site: Verify, Backup, Restore, Migrate, Clone, Disable, Reset Password
 * - Disabled site: Enable, Delete only
 * - Enabled platform: Verify, Lock, Migrate, Delete
 * - Locked platform: Verify, Unlock, Delete
 * - Server: Verify always
 */
class TaskAvailabilityResolver {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Returns available task definitions for the given entity.
   *
   * Each task definition is an array with keys:
   *   - 'label': Display label.
   *   - 'route': Route name for the task form.
   *   - 'route_params': Route parameters.
   *   - 'color': CSS class modifier (green, yellow, red, blue).
   *   - 'weight': Sorting weight.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The hosting entity.
   *
   * @return array
   *   Array of task definitions keyed by task type.
   */
  public function getAvailableTasks(ContentEntityInterface $entity): array {
    $entity_type = $entity->getEntityTypeId();

    return match ($entity_type) {
      'hosting_site' => $this->getSiteTasks($entity),
      'hosting_platform' => $this->getPlatformTasks($entity),
      'hosting_server' => $this->getServerTasks($entity),
      default => [],
    };
  }

  /**
   * Returns available tasks for a site entity.
   */
  protected function getSiteTasks(ContentEntityInterface $site): array {
    $status = (int) $site->get('status')->value;
    $id = $site->id();
    $tasks = [];

    if ($status === HostingSite::STATUS_ENABLED || $status === HostingSite::STATUS_QUEUED) {
      $tasks['verify'] = [
        'label' => t('Verify'),
        'route' => 'hosting_site.task.verify',
        'route_params' => ['hosting_site' => $id],
        'color' => 'green',
        'weight' => 0,
      ];
      $tasks['backup'] = [
        'label' => t('Backup'),
        'route' => 'hosting_site.task.backup',
        'route_params' => ['hosting_site' => $id],
        'color' => 'yellow',
        'weight' => 10,
      ];
      $tasks['restore'] = [
        'label' => t('Restore'),
        'route' => 'hosting_site.task.restore',
        'route_params' => ['hosting_site' => $id],
        'color' => 'yellow',
        'weight' => 11,
      ];
      $tasks['clone'] = [
        'label' => t('Clone'),
        'route' => 'hosting_site.task.clone',
        'route_params' => ['hosting_site' => $id],
        'color' => 'blue',
        'weight' => 20,
      ];
      $tasks['migrate'] = [
        'label' => t('Migrate'),
        'route' => 'hosting_site.task.migrate',
        'route_params' => ['hosting_site' => $id],
        'color' => 'yellow',
        'weight' => 21,
      ];
      $tasks['reset_password'] = [
        'label' => t('Reset password'),
        'route' => 'hosting_site.task.reset_password',
        'route_params' => ['hosting_site' => $id],
        'color' => 'green',
        'weight' => 30,
      ];
      $tasks['disable'] = [
        'label' => t('Disable'),
        'route' => 'hosting_site.task.disable',
        'route_params' => ['hosting_site' => $id],
        'color' => 'red',
        'weight' => 90,
      ];
    }
    elseif ($status === HostingSite::STATUS_DISABLED) {
      $tasks['enable'] = [
        'label' => t('Enable'),
        'route' => 'hosting_site.task.enable',
        'route_params' => ['hosting_site' => $id],
        'color' => 'green',
        'weight' => 0,
      ];
      $tasks['delete'] = [
        'label' => t('Delete'),
        'route' => 'hosting_site.task.delete',
        'route_params' => ['hosting_site' => $id],
        'color' => 'red',
        'weight' => 100,
      ];
    }

    // If settings allow delete without disable, add it for enabled sites too.
    $require_disable = \Drupal::config('hosting.settings')->get('require_disable_before_delete') ?? TRUE;
    if (!$require_disable && $status === HostingSite::STATUS_ENABLED && !isset($tasks['delete'])) {
      $tasks['delete'] = [
        'label' => t('Delete'),
        'route' => 'hosting_site.task.delete',
        'route_params' => ['hosting_site' => $id],
        'color' => 'red',
        'weight' => 100,
      ];
    }

    return $tasks;
  }

  /**
   * Returns available tasks for a platform entity.
   */
  protected function getPlatformTasks(ContentEntityInterface $platform): array {
    $status = (int) $platform->get('status')->value;
    $id = $platform->id();
    $tasks = [];

    // Verify is always available.
    $tasks['verify'] = [
      'label' => t('Verify'),
      'route' => 'hosting_platform.task.verify',
      'route_params' => ['hosting_platform' => $id],
      'color' => 'green',
      'weight' => 0,
    ];

    if ($status === HostingPlatform::STATUS_ENABLED) {
      $tasks['lock'] = [
        'label' => t('Lock'),
        'route' => 'hosting_platform.task.lock',
        'route_params' => ['hosting_platform' => $id],
        'color' => 'yellow',
        'weight' => 10,
      ];
      $tasks['migrate'] = [
        'label' => t('Migrate'),
        'route' => 'hosting_platform.task.migrate',
        'route_params' => ['hosting_platform' => $id],
        'color' => 'yellow',
        'weight' => 20,
      ];
    }
    elseif ($status === HostingPlatform::STATUS_LOCKED) {
      $tasks['unlock'] = [
        'label' => t('Unlock'),
        'route' => 'hosting_platform.task.unlock',
        'route_params' => ['hosting_platform' => $id],
        'color' => 'green',
        'weight' => 10,
      ];
    }

    $tasks['delete'] = [
      'label' => t('Delete'),
      'route' => 'hosting_platform.task.delete',
      'route_params' => ['hosting_platform' => $id],
      'color' => 'red',
      'weight' => 100,
    ];

    return $tasks;
  }

  /**
   * Returns available tasks for a server entity.
   */
  protected function getServerTasks(ContentEntityInterface $server): array {
    $id = $server->id();

    return [
      'verify' => [
        'label' => t('Verify'),
        'route' => 'hosting_server.task.verify',
        'route_params' => ['hosting_server' => $id],
        'color' => 'green',
        'weight' => 0,
      ],
    ];
  }

  /**
   * Builds a task action table render array for the given entity.
   *
   * Queries the last task execution per type and renders a table with
   * Run/View buttons, matching the D7 hosting_task_table() pattern.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The hosting entity.
   *
   * @return array
   *   Render array using #theme => 'hosting_task_table'.
   */
  public function buildTaskTable(ContentEntityInterface $entity): array {
    $tasks = $this->getAvailableTasks($entity);

    // Sort by weight.
    uasort($tasks, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

    // Look up context name to find last task per type.
    $context_name = $this->resolveContextName($entity);
    $last_tasks = [];
    if ($context_name) {
      $last_tasks = $this->fetchLastTasksByType($context_name);
    }

    $rows = [];
    foreach ($tasks as $type => $task) {
      $last = $last_tasks[$type] ?? NULL;

      // Determine status class from last task execution.
      $status_class = '';
      if ($last) {
        $status_class = $this->taskStatusToCssClass($last['status']);
      }

      // Check if task is currently queued/processing (not runnable again).
      $is_active = $last && in_array($last['status'], ['queued', 'processing'], TRUE);
      $permitted = !$is_active;

      $rows[] = [
        'task_type' => $type,
        'label' => $task['label'],
        'status_class' => $status_class,
        'run_url' => Url::fromRoute($task['route'], $task['route_params'])->toString(),
        'run_dialog' => TRUE,
        'view_url' => $last ? Url::fromRoute('entity.hosting_task.canonical', ['hosting_task' => $last['id']])->toString() : NULL,
        'permitted' => $permitted,
      ];
    }

    return [
      '#theme' => 'hosting_task_table',
      '#entity' => $entity,
      '#task_rows' => $rows,
      '#attached' => [
        'library' => ['core/drupal.dialog.ajax'],
      ],
    ];
  }

  /**
   * Resolves the context name for an entity.
   */
  protected function resolveContextName(ContentEntityInterface $entity): string {
    try {
      $context_storage = $this->entityTypeManager->getStorage('hosting_context');
      $records = $context_storage->loadByProperties([
        'entity_type' => $entity->getEntityTypeId(),
        'entity_id' => $entity->id(),
      ]);
      $record = $records ? reset($records) : NULL;
      return $record ? (string) $record->get('context_name')->value : '';
    }
    catch (\Exception) {
      return '';
    }
  }

  /**
   * Fetches the last task of each type for a context name.
   *
   * @return array
   *   Keyed by task_type, each value has 'id' and 'status'.
   */
  protected function fetchLastTasksByType(string $context_name): array {
    $result = [];
    try {
      $storage = $this->entityTypeManager->getStorage('hosting_task');
      $ids = $storage->getQuery()
        ->condition('context_name', $context_name)
        ->sort('id', 'DESC')
        ->accessCheck(TRUE)
        ->execute();

      if ($ids) {
        $tasks = $storage->loadMultiple($ids);
        foreach ($tasks as $task) {
          $type = $task->getTaskType();
          // Keep only the latest per type.
          if (!isset($result[$type])) {
            $result[$type] = [
              'id' => $task->id(),
              'status' => $task->getStatus(),
            ];
          }
        }
      }
    }
    catch (\Exception) {
      // Entity type may not exist.
    }
    return $result;
  }

  /**
   * Maps a task status string to a CSS class.
   */
  protected function taskStatusToCssClass(string $status): string {
    return match ($status) {
      'success' => 'hosting-success',
      'error' => 'hosting-error',
      'queued' => 'hosting-queued',
      'processing' => 'hosting-processing',
      'warning' => 'hosting-warning',
      default => '',
    };
  }

}
