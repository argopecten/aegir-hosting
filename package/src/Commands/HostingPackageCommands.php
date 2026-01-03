<?php

namespace Drupal\hosting_package\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Attributes as Drush;
use Drush\Commands\DrushCommands;
use Consolidation\AnnotatedCommand\Hooks\HookManager;

final class HostingPackageCommands extends DrushCommands {
  #[Drush\Hook(type: HookManager::PRE_COMMAND_HOOK, target: 'hosting:task')]
  public function preHostingTask(CommandData $commandData): void {
    $task = &drush_get_context('HOSTING_TASK');
    if (!$task) {
      return;
    }

    if ($task->ref instanceof \Drupal\Core\Entity\ContentEntityInterface && $task->ref->getEntityTypeId() === 'hosting_site') {
      // populate the profile option, if it hasn't been specified yet.
      if (empty($task->options['profile'])) {
        $profile_name = NULL;

        // If site node "profile_name" property is a string and not empty, lookup
        // the profile package NID and save it to the site node.
        if (empty($task->ref->get('profile')->value) && !empty($task->ref->profile_name) && is_string($task->ref->profile_name)) {
          $instances = hosting_package_instances_load(array(
            'i.rid' => $task->ref->get('platform')->target_id,
            'p.package_type' => 'profile',
            'p.short_name' => $task->ref->profile_name,
          ));

          // If instance is found, save the package ID to hosting_site table.
          $instance = current($instances);
          if (!empty($instance->short_name)) {
            \Drupal::database()->update('hosting_site')
              ->fields(array('profile' => $instance->short_name))
              ->condition('id', $task->ref->id())
              ->execute();

            $task->ref->set('profile', $instance->short_name);
            $profile_name = $instance->short_name;

            $this->logger()->success(dt('Updated site !id with install profile "!profile" with package ID !package_id.', array(
              '!id' => $task->ref->id(),
              '!profile' => $task->ref->profile_name,
              '!package_id' => $profile_name,
            )));
          }
          else {
            $this->logger()->warning(dt('Package for install profile "!profile" not found in platform !platform. Unable to update site profile field for site !site', array(
              '!platform' => $task->ref->get('platform')->target_id,
              '!site' => $task->ref->id(),
              '!profile' => $task->ref->profile_name,
            )));
          }
        }

        if (!$profile_name) {
          $profile_value = $task->ref->get('profile')->value;
          $profile = is_numeric($profile_value) ? \Drupal::entityTypeManager()->getStorage('hosting_package')->load($profile_value) : NULL;
          $profile_name = $profile ? $profile->get('short_name')->value : $profile_value;
        }
        $task_type = $task->get('task_type')->value ?? NULL;
        if ($task_type != 'import' && $task_type != 'delete' && $task_type != 'verify') {
          $task->options['profile'] = $profile_name;
        }
      }
    }
  }
}
