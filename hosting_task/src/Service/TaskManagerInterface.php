<?php

namespace Drupal\hosting_task\Service;

use Drupal\hosting_task\Entity\HostingTaskInterface;

interface TaskManagerInterface {

  public function createTask(string $context_name, string $task_type, array $args = [], array $options = []): HostingTaskInterface;

  public function queueTask(HostingTaskInterface $task): void;

  public function runTaskId(int $task_id): void;

}
