<?php
/**
 * @file
 * Implement drush hooks for the hosting migrate module.
 */

function drush_hosting_migrate_pre_hosting_task($task) {
  $task = &drush_get_context('HOSTING_TASK');
  if ($task->ref->type == 'site' && $task->task_type == 'migrate') {
    $platform = node_load($task->task_args['target_platform']);
    $task->args[2] = hosting_context_name($platform->nid);

    $site = $task->ref;

    if (hosting_site_get_domain($site->title) != hosting_site_get_domain($task->task_args['new_uri'])) {
      $task->args[3] = '@' . hosting_site_get_domain($task->task_args['new_uri']);
    }

    if ($site->db_server != $task->task_args['new_db_server']) {
      $task->options['new_db_server'] = hosting_context_name($task->task_args['new_db_server']);
    }


    $profile_instance = _hosting_migrate_get_target_profile_name($task);
    if (is_object($profile_instance) && isset($profile_instance->short_name)) {
      $task->options['profile'] = $profile_instance->short_name;
    }
    else {
      drush_set_error("HOSTING_NO_VALID_PROFILE", dt("There are no valid install profiles on the target platform to migrate to"));
    }
  }
}

function _hosting_migrate_get_target_profile_name($task) {
  // load the original install profile
  $profile = node_load($task->ref->profile);

  // get the profile instance we are migrating to.
  $profile_instance = hosting_package_instance_load(array(
    'i.rid' => $task->task_args['target_platform'],
    'p.short_name' => $profile->short_name,
  ));

  if (empty($profile_instance)) {
    // get a possible upgrade path for the profile.
    $profile_instance = hosting_package_instance_load(array(
      'i.rid' => $task->task_args['target_platform'],
      'p.old_short_name' => $profile->short_name,
    ));
  }

  return $profile_instance;

}

/**
 * @todo Please document this function.
 * @see http://drupal.org/node/1354
 */
function hosting_migrate_post_hosting_migrate_task($task, $data) {
  if ($task->ref->type == 'site') {
    $target = $task->task_args['target_platform'];
    $site = $task->ref;

    if (hosting_site_get_domain($site->title) != hosting_site_get_domain($task->task_args['new_uri'])) {
      $site->title = hosting_site_clean_domain($task->task_args['new_uri']);
      hosting_context_register($site->nid, hosting_site_get_domain($site->title));
    }

    if ($site->db_server != $task->task_args['new_db_server']) {
      $site->db_server = $task->task_args['new_db_server'];
    }
    $site->db_name = $data['context']['db_name'];

    $profile = hosting_package_instance_load(array(
      'i.rid' => $target,
      'p.short_name' => $data['context']['profile'],
    ));
    if ($profile) {
      $site->profile = $profile->package_id;
    }

    $site->verified = REQUEST_TIME;
    $site->no_verify = TRUE;
    $site->platform = $target;
    node_save($site);

    $task->ref = $site;

    //do the package synching
    $context = $data['context'];
    $packages = $context['packages'];
    hosting_package_sync($packages);
    hosting_package_instance_sync($task->ref->nid, $task->ref->type, $packages);

    // Record the backup created during migrate.
    $task->task_args['description'] = t('Pre-migration backup');
    hosting_site_post_hosting_backup_task($task, $data);
  }
}
