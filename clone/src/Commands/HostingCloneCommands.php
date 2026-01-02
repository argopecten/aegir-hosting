<?php

namespace Drupal\hosting_clone\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Attributes as Drush;
use Drush\Commands\DrushCommands;
use Drush\HookManager\HookManager;

final class HostingCloneCommands extends DrushCommands {
  #[Drush\Hook(type: HookManager::PRE_COMMAND, target: 'hosting:task')]
  public function preHostingTask(CommandData $commandData): void {
    $task = &drush_get_context('HOSTING_TASK');
    if (!$task) {
      return;
    }

    if (hosting_entity_task_scope($task->ref) == 'site' && $task->get('task_type')->value == 'clone') {
      $task->args[2] = '@' . hosting_site_get_domain($task->task_args['new_uri']);
      $platform = \Drupal::entityTypeManager()->getStorage('hosting_platform')->load($task->task_args['target_platform']);
      $task->args[3] = $platform ? hosting_context_name($platform->id()) : NULL;

      $profile_instance = _hosting_migrate_get_target_profile_name($task);
      if (is_object($profile_instance) && isset($profile_instance->short_name)) {
        $task->options['profile'] = $profile_instance->short_name;
      }
      else {
        drush_set_error("HOSTING_NO_VALID_PROFILE", dt("There are no valid install profiles on the target platform to migrate to"));
      }

      $db_server_id = $task->ref instanceof \Drupal\Core\Entity\ContentEntityInterface && $task->ref->hasField('db_server') ? $task->ref->get('db_server')->target_id : NULL;
      if ($db_server_id != $task->task_args['new_db_server']) {
        $task->options['new_db_server'] = hosting_context_name($task->task_args['new_db_server']);
      }

      if (\Drupal::moduleHandler()->moduleExists('hosting_alias')) {
        if (isset($task->task_args['aliases'])) {
          $task->options['aliases'] = str_replace("\n", ",", $task->task_args['aliases']);
        }
        if (isset($task->task_args['redirection'])) {
          $task->options['redirection'] = $task->task_args['redirection'];
        }
      }
    }
  }
}
