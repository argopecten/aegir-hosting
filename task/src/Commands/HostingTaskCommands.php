<?php

namespace Drupal\hosting_task\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Attributes as Drush;
use Drush\Commands\DrushCommands;
use Consolidation\AnnotatedCommand\Hooks\HookManager;

final class HostingTaskCommands extends DrushCommands {
  #[Drush\Hook(type: HookManager::INITIALIZE)]
  public function init(CommandData $commandData): void {
    // Update a task's status after Drush operations are complete.
    register_shutdown_function([static::class, 'updateStatus']);
  }

  /**
   * Shutdown function to catch any task status.
   */
  public static function updateStatus(): void {
    $task = drush_get_context('HOSTING_TASK');
    if (!empty($task)) {
      $message = _hosting_parse_error_code(hosting_task_update_status($task));
      \Drush\Drush::logger()->info(dt('Updated task status to "!log"', array('!log' => $message)));
    }
  }
}
