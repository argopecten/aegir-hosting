<?php

namespace Drupal\hosting_alias\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Attributes as Drush;
use Drush\Commands\DrushCommands;
use Consolidation\AnnotatedCommand\Hooks\HookManager;

final class HostingAliasCommands extends DrushCommands {
  #[Drush\Hook(type: HookManager::PRE_COMMAND_HOOK, target: 'hosting:task')]
  public function preHostingTask(CommandData $commandData): void {
    if (!\Drupal::moduleHandler()->moduleExists('hosting_alias')) {
      return;
    }

    $task = &drush_get_context('HOSTING_TASK');
    if (!$task) {
      return;
    }

    if (hosting_entity_task_scope($task->ref) == 'site') {
      $aliases = implode(',', hosting_alias_get_aliases($task->ref));
      $task->context_options['aliases'] = $aliases ? $aliases : 'null';

      $redirection = \Drupal::database()->query(
        "SELECT redirection FROM {hosting_site_alias} WHERE site_id = :site_id",
        [':site_id' => hosting_entity_id($task->ref)]
      )->fetchField();
      $task->context_options['redirection'] = $redirection ? $redirection : 'null';
    }
  }
}
