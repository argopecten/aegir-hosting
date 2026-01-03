<?php

namespace Drupal\hosting_subdirs\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Attributes as Drush;
use Drush\Commands\DrushCommands;
use Consolidation\AnnotatedCommand\Hooks\HookManager;

final class HostingSubdirsCommands extends DrushCommands {
  #[Drush\Hook(type: HookManager::PRE_COMMAND_HOOK, target: 'hosting:task')]
  public function preHostingTask(CommandData $commandData): void {
    $task = &drush_get_context('HOSTING_TASK');
    if (!$task) {
      return;
    }

    if (hosting_entity_task_scope($task->ref) == 'site' && $task->get('task_type')->value == 'migrate') {
      $site = $task->ref;

      if (!_hosting_subdirs_is_a_subdir_site($site)) {
        return;
      }

      // Clean up the automatic subdir alias and redirection, as they'll be
      // re-generated with the new domain.
      $subdir_alias = array_search($site->redirection, $site->aliases);
      unset($site->aliases[$subdir_alias]);
      unset($site->redirection);
    }
  }

  #[Drush\Hook(type: HookManager::POST_COMMAND_HOOK, target: 'hosting:task')]
  public function postHostingTask(CommandData $commandData): void {
    $task = &drush_get_context('HOSTING_TASK');
    if (!$task) {
      return;
    }

    if (hosting_entity_task_scope($task->ref) == 'site' && $task->get('task_type')->value == 'migrate') {
      $site = $task->ref;

      if (!_hosting_subdirs_is_a_subdir_site($site)) {
        return;
      }

      // Trigger a new verify to re-sync the front-end aliases into the back-end
      // context, so that the subdir redirection works.
      hosting_add_task(hosting_entity_id($site), 'verify');
    }
  }
}
