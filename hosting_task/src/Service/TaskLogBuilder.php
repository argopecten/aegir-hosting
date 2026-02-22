<?php

namespace Drupal\hosting_task\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hosting_task\Entity\HostingTask;

/**
 * Builds render arrays for hosting task logs.
 */
class TaskLogBuilder {

  use StringTranslationTrait;

  /**
   * Maximum message length before collapsing into details/summary.
   */
  protected const MAX_MESSAGE_LENGTH = 300;

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Build the task log render array for a hosting_task entity.
   *
   * @param \Drupal\hosting_task\Entity\HostingTask $task
   *   The hosting task entity.
   *
   * @return array
   *   A render array using the hosting_task_log theme hook.
   */
  public function build(HostingTask $task): array {
    $storage = $this->entityTypeManager->getStorage('hosting_task_log');
    $query = $storage->getQuery()
      ->condition('task', $task->id())
      ->sort('timestamp', 'ASC')
      ->sort('id', 'ASC')
      ->accessCheck(TRUE);
    $ids = $query->execute();

    if (!$ids) {
      return [
        '#theme' => 'hosting_task_log',
        '#task' => $task,
        '#rows' => [],
        '#jump_links' => [],
        '#attributes' => ['id' => 'hosting-task-log-wrapper'],
      ];
    }

    $entries = $storage->loadMultiple($ids);
    $rows = [];
    $has_error = FALSE;
    $has_warning = FALSE;
    $started = (int) $task->get('started')->value;

    foreach ($entries as $entry) {
      $type = (string) $entry->get('type')->value;
      $message = (string) $entry->get('message')->value;
      $timestamp = (int) $entry->get('timestamp')->value;
      $error_text = (string) $entry->get('error')->value;

      // Compute execution time relative to task start.
      $exec_time = ($started && $timestamp >= $started) ? ($timestamp - $started) : NULL;

      // Determine row CSS class based on log type.
      $row_class = match ($type) {
        'error' => 'hosting-error',
        'warning' => 'hosting-warning',
        'success', 'ok' => 'hosting-success',
        default => '',
      };

      // Track whether we have errors/warnings for jump links.
      if ($type === 'error') {
        $has_error = TRUE;
      }
      if ($type === 'warning') {
        $has_warning = TRUE;
      }

      // Combine message and error text if both exist.
      $full_message = $message;
      if ($error_text !== '') {
        $full_message .= "\n" . $error_text;
      }

      // Long messages get collapsed with details/summary.
      $is_long = mb_strlen($full_message) > self::MAX_MESSAGE_LENGTH;
      $summary = $is_long ? mb_substr($full_message, 0, self::MAX_MESSAGE_LENGTH) : $full_message;

      $rows[] = [
        'message' => $full_message,
        'type' => $type,
        'timestamp' => $timestamp,
        'exec_time' => $exec_time,
        'row_class' => $row_class,
        'is_long' => $is_long,
        'summary' => $summary,
      ];
    }

    // Build jump links.
    $jump_links = [];
    if ($has_error) {
      $jump_links[] = [
        'anchor' => 'error',
        'label' => $this->t('Jump to error'),
        'type' => 'error',
      ];
    }
    if ($has_warning) {
      $jump_links[] = [
        'anchor' => 'warning',
        'label' => $this->t('Jump to first warning'),
        'type' => 'warning',
      ];
    }

    return [
      '#theme' => 'hosting_task_log',
      '#task' => $task,
      '#rows' => $rows,
      '#jump_links' => $jump_links,
      '#attributes' => ['id' => 'hosting-task-log-wrapper'],
      '#cache' => [
        'tags' => $task->getCacheTags(),
      ],
    ];
  }

}
