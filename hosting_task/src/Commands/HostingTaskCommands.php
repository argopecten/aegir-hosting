<?php

namespace Drupal\hosting_task\Commands;

use Drupal\hosting_task\Service\TaskManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

class HostingTaskCommands extends DrushCommands {

  protected TaskManagerInterface $taskManager;

  public function __construct(TaskManagerInterface $taskManager) {
    $this->taskManager = $taskManager;
  }

  #[CLI\Command(name: 'hosting:task', aliases: ['hosting-task'])]
  #[CLI\Description('Create and queue a hosting task.')]
  #[CLI\Argument(name: 'context', description: 'Context name, e.g. @example.com')]
  #[CLI\Argument(name: 'task', description: 'Task type, e.g. install, verify, backup')]
  public function task(string $context, string $task): void {
    $context = ltrim($context, '@');
    $taskEntity = $this->taskManager->createTask($context, $task);
    $this->logger()->success(sprintf('Queued task %s (%d).', $taskEntity->label(), $taskEntity->id()));
  }

}
