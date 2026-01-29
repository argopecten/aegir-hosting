<?php

namespace Drupal\hosting_task\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\hosting_task\Service\TaskManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for task queue management.
 */
class TaskQueueController extends ControllerBase {

  protected TaskManagerInterface $taskManager;

  public function __construct(TaskManagerInterface $taskManager) {
    $this->taskManager = $taskManager;
  }

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('hosting.task_manager')
    );
  }

  /**
   * Display the task queue dashboard.
   */
  public function dashboard(): array {
    $storage = $this->entityTypeManager()->getStorage('hosting_task');
    
    // Get queue statistics.
    $stats = [
      'queued' => $storage->getQuery()
        ->condition('status', 'queued')
        ->accessCheck(FALSE)
        ->count()
        ->execute(),
      'processing' => $storage->getQuery()
        ->condition('status', 'processing')
        ->accessCheck(FALSE)
        ->count()
        ->execute(),
      'failed' => $storage->getQuery()
        ->condition('status', 'failed')
        ->accessCheck(FALSE)
        ->count()
        ->execute(),
      'success' => $storage->getQuery()
        ->condition('status', 'success')
        ->accessCheck(FALSE)
        ->count()
        ->execute(),
    ];

    // Get recent tasks.
    $task_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->sort('id', 'DESC')
      ->range(0, 50)
      ->execute();

    $tasks = $storage->loadMultiple($task_ids);

    // Build table.
    $rows = [];
    foreach ($tasks as $task) {
      $started = $task->get('started')->value;
      $completed = $task->get('completed')->value;
      $duration = $started && $completed ? $completed - $started : NULL;

      $row = [
        'id' => $task->id(),
        'type' => $task->getTaskType(),
        'context' => $task->get('context_name')->value,
        'status' => [
          'data' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $task->getStatus(),
            '#attributes' => [
              'class' => ['task-status', 'task-status--' . $task->getStatus()],
            ],
          ],
        ],
        'retry' => $task->get('retry_count')->value . '/' . $task->get('max_retries')->value,
        'duration' => $duration ? $this->dateFormatter()->formatInterval($duration) : '-',
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => $this->getTaskOperations($task),
          ],
        ],
      ];

      $rows[] = $row;
    }

    $build = [];

    // Statistics cards.
    $build['stats'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['task-queue-stats']],
    ];

    foreach ($stats as $status => $count) {
      $build['stats'][$status] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['stat-card', 'stat-card--' . $status]],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => ucfirst($status),
          '#attributes' => ['class' => ['stat-label']],
        ],
        'count' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $count,
          '#attributes' => ['class' => ['stat-count']],
        ],
      ];
    }

    // Tasks table.
    $build['tasks'] = [
      '#type' => 'table',
      '#header' => [
        'id' => $this->t('ID'),
        'type' => $this->t('Type'),
        'context' => $this->t('Context'),
        'status' => $this->t('Status'),
        'retry' => $this->t('Retries'),
        'duration' => $this->t('Duration'),
        'operations' => $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No tasks found.'),
      '#attributes' => ['class' => ['task-queue-table']],
    ];

    // Auto-refresh.
    $build['#attached']['library'][] = 'hosting_task/task-queue';
    $build['#attached']['drupalSettings']['taskQueue']['refreshUrl'] = Url::fromRoute('hosting_task.queue.refresh')->toString();

    return $build;
  }

  /**
   * Get operations for a task.
   */
  protected function getTaskOperations($task): array {
    $operations = [];

    $operations['view'] = [
      'title' => $this->t('View'),
      'url' => Url::fromRoute('entity.hosting_task.canonical', ['hosting_task' => $task->id()]),
    ];

    // Retry failed tasks.
    if ($task->getStatus() === 'failed') {
      $operations['retry'] = [
        'title' => $this->t('Retry'),
        'url' => Url::fromRoute('hosting_task.queue.retry', ['task' => $task->id()]),
      ];
    }

    // Cancel queued or processing tasks.
    if (in_array($task->getStatus(), ['queued', 'processing', 'failed'])) {
      $operations['cancel'] = [
        'title' => $this->t('Cancel'),
        'url' => Url::fromRoute('hosting_task.queue.cancel', ['task' => $task->id()]),
        'attributes' => ['class' => ['use-ajax']],
      ];
    }

    return $operations;
  }

  /**
   * Refresh task queue data (AJAX endpoint).
   */
  public function refresh(Request $request): JsonResponse {
    $storage = $this->entityTypeManager()->getStorage('hosting_task');
    
    // Get recent tasks.
    $task_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->sort('id', 'DESC')
      ->range(0, 50)
      ->execute();

    $tasks = $storage->loadMultiple($task_ids);

    $data = [];
    foreach ($tasks as $task) {
      $started = $task->get('started')->value;
      $completed = $task->get('completed')->value;
      $duration = $started && $completed ? $completed - $started : NULL;

      $data[] = [
        'id' => $task->id(),
        'type' => $task->getTaskType(),
        'context' => $task->get('context_name')->value,
        'status' => $task->getStatus(),
        'retry_count' => $task->get('retry_count')->value,
        'max_retries' => $task->get('max_retries')->value,
        'duration' => $duration,
      ];
    }

    return new JsonResponse($data);
  }

  /**
   * Cancel a task.
   */
  public function cancel(int $task): RedirectResponse {
    $current_user = $this->currentUser();
    
    if ($this->taskManager->cancelTask($task, $current_user->id())) {
      $this->messenger()->addStatus($this->t('Task @id has been cancelled.', ['@id' => $task]));
    }
    else {
      $this->messenger()->addError($this->t('Failed to cancel task @id.', ['@id' => $task]));
    }

    return new RedirectResponse(Url::fromRoute('hosting_task.queue.dashboard')->toString());
  }

  /**
   * Retry a failed task.
   */
  public function retry(int $task): RedirectResponse {
    if ($this->taskManager->retryTask($task)) {
      $this->messenger()->addStatus($this->t('Task @id has been queued for retry.', ['@id' => $task]));
    }
    else {
      $this->messenger()->addError($this->t('Failed to retry task @id.', ['@id' => $task]));
    }

    return new RedirectResponse(Url::fromRoute('hosting_task.queue.dashboard')->toString());
  }

}
