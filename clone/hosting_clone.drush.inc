<?php
/**
 * @file
 *   Drush include for the site cloning module.
 */

function drush_hosting_clone_pre_hosting_task($task) {
  $task = &drush_get_context('HOSTING_TASK');
  if ($task->ref->type == 'site' && $task->task_type == 'clone') {
    $task->args[2] = '@' . hosting_site_get_domain($task->task_args['new_uri']);
    $platform = node_load($task->task_args['target_platform']);
    $task->args[3] = hosting_context_name($platform->nid);

    $profile_instance = _hosting_migrate_get_target_profile_name($task);
    if (is_object($profile_instance) && isset($profile_instance->short_name)) {
      $task->options['profile'] = $profile_instance->short_name;
    }
    else {
      drush_set_error("HOSTING_NO_VALID_PROFILE", dt("There are no valid install profiles on the target platform to migrate to"));
    }

    if ($task->ref->db_server != $task->task_args['new_db_server']) {
      $task->options['new_db_server'] = hosting_context_name($task->task_args['new_db_server']);
    }

    if (module_exists('hosting_alias')) {
      if (isset($task->task_args['aliases'])) {
        $task->options['aliases'] = str_replace("\n", ",", $task->task_args['aliases']);
      }
      if (isset($task->task_args['redirection'])) {
        $task->options['redirection'] = $task->task_args['redirection'];
      }
    }
  }
}


/**
 * @todo Please document this function.
 * @see http://drupal.org/node/1354
 */
function hosting_clone_post_hosting_clone_task($task, $data) {
  if ($task->ref->type == 'site') {
    $target = $task->task_args['target_platform'];
    $clone = new stdClass();
    # copy some of the settings of the cloned site ($task->ref) to $clone
    foreach (array('type', 'status', 'uid', 'comment', 'promote', 'moderate', 'sticky', 'name', 'format', 'client', 'db_server', 'profile', 'site_status', 'site_language') as $field) {
      $clone->$field = $task->ref->$field;
    }
    $clone->title = hosting_site_clean_domain($task->task_args['new_uri']);
    $clone->hosting_name = hosting_site_get_domain($clone->title);
    $clone->platform = $target;
    $clone->import = 1; # make sure the site doesn't reinstall...
    $clone->verified = 0; # ... and it does verify
    $clone->aliases = isset($task->task_args['aliases']) ? $task->task_args['aliases'] : array();
    $clone->redirection = isset($task->task_args['redirection']) ? $task->task_args['redirection'] : FALSE;

    if ($clone->db_server != $task->task_args['new_db_server']) {
      $clone->db_server = $task->task_args['new_db_server'];
    }

    $profile = hosting_package_instance_load(array(
      'i.rid' => $target,
      'p.short_name' => $data['context']['profile'],
    ));
    if ($profile) {
      $clone->profile = $profile->package_id;
    }

    node_save($clone);

    // Record the backup created during clone.
    $task->task_args['description'] = t('Pre-clone backup');
    hosting_site_post_hosting_backup_task($task, $data);
  }
}
