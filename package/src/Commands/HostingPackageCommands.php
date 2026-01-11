<?php

namespace Drupal\hosting_package\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Attributes as Drush;
use Drush\Commands\DrushCommands;
use Consolidation\AnnotatedCommand\Hooks\HookManager;

final class HostingPackageCommands extends DrushCommands {
  #[Drush\Hook(type: HookManager::PRE_COMMAND_HOOK, target: 'hosting:task')]
  public function preHostingTask(CommandData $commandData): void {
    $context = hosting_task_get_current();
    if (!$context) {
      return;
    }

    $ref = $context->getRef();
    if ($ref instanceof \Drupal\Core\Entity\ContentEntityInterface && $ref->getEntityTypeId() === 'hosting_site') {
      // populate the profile option, if it hasn't been specified yet.
      $options = $context->getOptions();
      if (empty($options['profile'])) {
        $profile_name = NULL;

        // If site node "profile_name" property is a string and not empty, lookup
        // the profile package NID and save it to the site node.
        if (empty($ref->get('profile')->value) && !empty($ref->profile_name) && is_string($ref->profile_name)) {
          $instances = hosting_package_instances_load(array(
            'i.rid' => $ref->get('platform')->target_id,
            'p.package_type' => 'profile',
            'p.short_name' => $ref->profile_name,
          ));

          // If instance is found, save the package ID to hosting_site table.
          $instance = current($instances);
          if (!empty($instance->short_name)) {
            \Drupal::database()->update('hosting_site')
              ->fields(array('profile' => $instance->short_name))
              ->condition('id', $ref->id())
              ->execute();

            $ref->set('profile', $instance->short_name);
            $profile_name = $instance->short_name;

            $this->logger()->success((string) t('Updated site @id with install profile "@profile" with package ID @package_id.', [
              '@id' => $ref->id(),
              '@profile' => $ref->profile_name,
              '@package_id' => $profile_name,
            ]));
          }
          else {
            $this->logger()->warning((string) t('Package for install profile "@profile" not found in platform @platform. Unable to update site profile field for site @site', [
              '@platform' => $ref->get('platform')->target_id,
              '@site' => $ref->id(),
              '@profile' => $ref->profile_name,
            ]));
          }
        }

        if (!$profile_name) {
          $profile_value = $ref->get('profile')->value;
          $profile = is_numeric($profile_value) ? \Drupal::entityTypeManager()->getStorage('hosting_package')->load($profile_value) : NULL;
          $profile_name = $profile ? $profile->get('short_name')->value : $profile_value;
        }
        $task = $context->getTask();
        $task_type = $task->get('task_type')->value ?? NULL;
        if ($task_type != 'import' && $task_type != 'delete' && $task_type != 'verify') {
          $options['profile'] = $profile_name;
          $context->setOptions($options);
        }
      }
    }
  }
}
