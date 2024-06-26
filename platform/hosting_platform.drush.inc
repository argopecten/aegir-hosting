<?php
/**
 * @file
 * Implement drush hooks for the Platforms module.
 */

/**
 * Implements hook_hosting_TASK_OBJECT_context_options().
 */
function hosting_hosting_platform_context_options(&$task) {
  $task->context_options['server'] = '@server_master';
  $task->context_options['web_server'] = hosting_context_name($task->ref->web_server);
  // Trim the path so we dont pass extra spaces.
  $task->context_options['root'] = trim($task->ref->publish_path, " ");
  if ($task->ref->makefile) {
    $task->context_options['makefile'] = $task->ref->makefile;
  }
  if (isset($task->ref->make_working_copy) && !empty($task->ref->make_working_copy)) {
    $task->context_options['make_working_copy'] = $task->ref->make_working_copy;
  }
  else {
    $task->context_options['make_working_copy'] = 'null';
  }
}

/**
 * Implements hook_drush_context_import().
 */
function hosting_platform_drush_context_import($context, &$node) {
  if ($context->type == 'platform') {
    if (!isset($node->title)) {
      $node->title = str_replace('platform_', '', trim($context->name, '@'));
    }
    $node->web_server = hosting_context_nid($context->web_server);
    $node->publish_path = $context->root;
    $node->makefile = $context->makefile;
    if (isset($context->make_working_copy) && !empty($context->make_working_copy)) {
      $node->make_working_copy = $context->make_working_copy;
    }
  }
}

/**
 * Implements hook_post_hosting_TASK_TYPE_task().
 *
 * Sets the platform verified timestamp, to discren when it was verified.
 * Imports all the profiles and modules into package and package release nodes.
 */
function hosting_platform_post_hosting_verify_task($task, $data) {
  $node = $task->ref;
  if ($node->type == 'platform') {
    $context = $data['context'];
    $packages = $context['packages'];

    // Lock platforms by default.
    if ($node->verified == 0 && variable_get('hosting_lock_platforms_by_default', FALSE)) {
      $node->platform_status = HOSTING_PLATFORM_LOCKED;
    }

    // Set verified flag on platform, to let it know it has been checked.
    $node->verified = REQUEST_TIME;

    /**
     * If we are verifying a Locked platform (i.e if the publish_path has changed),
     * don't reset the status to Enabled. We don't need to check whether a platform
     * is deleted here for the same reason, because we don't allow a deleted platform
     * to be reverified.
     */
    if ($node->platform_status != HOSTING_PLATFORM_LOCKED) {
      $node->platform_status = HOSTING_PLATFORM_ENABLED;
    }
    $node->no_verify = TRUE;
    // Save the platform being verified.
    node_save($node);

    hosting_package_sync($packages['base']);
    hosting_package_sync($packages['sites-all']);
    hosting_package_instance_sync($node->nid, $task->ref->type, $packages['base'], $packages['sites-all']);
    foreach ($context['profiles'] as $profile) {
      hosting_package_sync($packages['profiles'][$profile]);
      $instance = hosting_package_instance_load(
        array('p.short_name' => $profile, 'i.rid' => $node->nid, 'p.package_type' => 'profile'));
      hosting_package_instance_sync($instance->iid, $instance->package_type, $packages['base'], $packages['profiles'][$profile], $packages['sites-all']);

      // @todo : package instances of modules to profiles. <-- huh?
    }

    // If configured to do so, import all sites found on this platform.
    if (variable_get('hosting_platform_automatic_site_import', TRUE)) {
      foreach ($context['sites'] as $url) {
        if (hosting_domain_allowed($url)) {
          // Import any sites that have not been created yet.
          $site = new stdClass();
          $site->type = 'site';
          $site->site_status = HOSTING_SITE_QUEUED;
          $site->import = TRUE;
          $site->title = $url;
          $site->platform = $node->nid;
          $site->client = HOSTING_DEFAULT_CLIENT;
          $site->db_server = HOSTING_DEFAULT_DB_SERVER;
          node_save($site);
          drush_log(dt("Imported existing site !domain", array('!domain' => _hosting_node_link($site->nid))));
          hosting_package_instance_sync($site->nid, $site->type, $packages['base']);
        }
      }
    }
  }

  // When a server is verified, queue up a verify task for every platform.
  if ($task->ref->type == 'server') {
    $query = db_query("SELECT nid FROM {hosting_platform} WHERE status <> :status AND web_server = :web_server", array(':status' => HOSTING_PLATFORM_DELETED, ':web_server' => $task->ref->nid));
    while ($nid = $query->fetch()) {
      hosting_add_task($nid->nid, 'verify');
    }
  }
}

/**
 * Implements hook_hosting_post_DELETE().
 */
function hosting_platform_post_hosting_delete_task($task, $data) {
  $task->ref->platform_status = HOSTING_PLATFORM_DELETED;
  $task->ref->no_verify = TRUE;
  node_save($task->ref);
  hosting_context_delete($task->ref->nid);
  db_delete('hosting_platform_client_access')
    ->condition('pid', $task->ref->nid)
    ->execute();
  // Clean up package instances.
  hosting_package_instance_sync($task->ref->nid, 'platform');
}

/**
 * @todo Please document this function.
 * @see http://drupal.org/node/1354
 */
function hosting_platform_post_hosting_lock_task($task, $data) {
  $task->ref->platform_status = HOSTING_PLATFORM_LOCKED;
  $task->ref->no_verify = TRUE;
  node_save($task->ref);
}

/**
 * @todo Please document this function.
 * @see http://drupal.org/node/1354
 */
function hosting_platform_post_hosting_unlock_task($task, $data) {
  $task->ref->platform_status = HOSTING_PLATFORM_ENABLED;
  $task->ref->no_verify = TRUE;
  node_save($task->ref);
}
