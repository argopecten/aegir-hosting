<?php

namespace Drupal\hosting_site\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Attributes as Drush;
use Drush\Commands\DrushCommands;
use Drush\HookManager\HookManager;

final class HostingSiteCommands extends DrushCommands {
  #[Drush\Hook(type: HookManager::PRE_COMMAND, target: 'hosting:task')]
  public function preHostingTask(CommandData $commandData): void {
    $task = &drush_get_context('HOSTING_TASK');
    if (!$task) {
      return;
    }

    $task_type = $task->get('task_type')->value ?? NULL;
    if ($task_type == 'restore') {
      $backup = hosting_site_get_backup($task->task_args['bid']);
      $task->args[1] = $backup['filename'];
    }

    if ($task_type == 'backup-delete') {
      $backups = [];
      foreach ($task->task_args as $bid => $filename) {
        if ($filename !== '0') {
          $backups[] = $filename;
        }
      }
      $task->args[1] = implode(',', $backups);
    }
  }
}
