<?php

namespace Drupal\hosting_task\Service;

use Drupal\hosting_task\Entity\HostingTaskInterface;

interface TaskManagerInterface {

  /**
   * Create and queue a new task.
   *
   * @param string $context_name
   *   The provision context name.
   * @param string $task_type
   *   The task type (e.g., 'save', 'verify', 'install').
   * @param array $args
   *   Positional arguments for the backend command.
   * @param array $options
   *   Options for the backend command.
   * @param string|null $chain_next
   *   Optional task type to queue after this task succeeds.
   *
   * @return \Drupal\hosting_task\Entity\HostingTaskInterface
   *   The created task entity.
   */
  public function createTask(string $context_name, string $task_type, array $args = [], array $options = [], ?string $chain_next = NULL): HostingTaskInterface;

  public function queueTask(HostingTaskInterface $task): void;

  public function runTaskId(int $task_id): void;

}
