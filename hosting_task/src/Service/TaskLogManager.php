<?php

namespace Drupal\hosting_task\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class TaskLogManager {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected TimeInterface $time;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, TimeInterface $time) {
    $this->entityTypeManager = $entityTypeManager;
    $this->time = $time;
  }

  public function log(int $task_id, string $type, string $message, string $error = ''): void {
    $storage = $this->entityTypeManager->getStorage('hosting_task_log');
    $entry = $storage->create([
      'task' => $task_id,
      'type' => $type,
      'message' => $message,
      'error' => $error,
      'timestamp' => $this->time->getRequestTime(),
    ]);
    $entry->save();
  }

}
