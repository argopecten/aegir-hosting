<?php

namespace Drupal\hosting_clone\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Attributes as Drush;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Consolidation\AnnotatedCommand\Hooks\HookManager;

final class HostingCloneCommands extends DrushCommands {
  #[Drush\Hook(type: HookManager::PRE_COMMAND_HOOK, target: 'hosting:task')]
  public function preHostingTask(CommandData $commandData): void {
    $context = hosting_task_get_current();
    if (!$context) {
      return;
    }

    $task = $context->getTask();
    $ref = $context->getRef();
    if (hosting_entity_task_scope($ref) == 'site' && $task->get('task_type')->value == 'clone') {
      $args = $context->getArgs();
      $args[2] = '@' . hosting_site_get_domain($task->task_args['new_uri']);
      $platform = \Drupal::entityTypeManager()->getStorage('hosting_platform')->load($task->task_args['target_platform']);
      $args[3] = $platform ? hosting_context_name($platform->id()) : NULL;
      $context->setArgs($args);

      $profile_instance = _hosting_migrate_get_target_profile_name($context);
      if (is_object($profile_instance) && isset($profile_instance->short_name)) {
        $options = $context->getOptions();
        $options['profile'] = $profile_instance->short_name;
        $context->setOptions($options);
      }
      else {
        throw new UserAbortException((string) t('There are no valid install profiles on the target platform to migrate to.'));
      }

      $db_server_id = $ref instanceof \Drupal\Core\Entity\ContentEntityInterface && $ref->hasField('db_server') ? $ref->get('db_server')->target_id : NULL;
      if ($db_server_id != $task->task_args['new_db_server']) {
        $options = $context->getOptions();
        $options['new_db_server'] = hosting_context_name($task->task_args['new_db_server']);
        $context->setOptions($options);
      }

      if (\Drupal::moduleHandler()->moduleExists('hosting_alias')) {
        $options = $context->getOptions();
        if (isset($task->task_args['aliases'])) {
          $options['aliases'] = str_replace("\n", ",", $task->task_args['aliases']);
        }
        if (isset($task->task_args['redirection'])) {
          $options['redirection'] = $task->task_args['redirection'];
        }
        $context->setOptions($options);
      }
    }
  }
}
