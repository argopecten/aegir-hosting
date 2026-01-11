<?php
/**
 * @file
 * Hooks provided by the hosting module, and some other random ones.
 */

/** @defgroup hostinghooks Frontend hooks
 * @{
 *
 * Those hooks are hooks usable within contrib Drupal modules running
 * in the Aegir frontend site.
 */

/**
 * Determine if a site can be created using the specified domain.
 *
 * The frontend will only create a specified domains if all implementations of
 * this hook return TRUE, so in most cases you will be looking for domains that
 * you don't allow, and fallback to a default position of allowing the domain.
 *
 * @param string $url
 *   The URL of the site that hosting wishes to create.
 * @param array $params
 *   An array of paramters that may contain information about the site. None of
 *   the keys are required however, so you should not depend on the value of any
 *   particular key in this array. If the array is not empty it will usually
 *   contain at least an 'id' key whose value is the entity ID of the site
 *   being created.
 *
 * @return bool
 *   Return TRUE/FALSE if you allow or deny the domain respectively.
 *
 * @see hosting_domain_allowed()
 */
function hook_allow_domain($url, $params) {
  // Don't allow another drupal.org, it's special.
  if ($url == 'drupal.org') {
    return FALSE;
  }
  else {
    return TRUE;
  }
}

/**
 * Import a backend context into the corresponding frontend node.
 *
 * This hook will be invoked when an object is being imported from the backend
 * into the frontend, for example a site that has just been cloned. You should
 * inspect the context coming from the backend and store anything the frontend
 * that you need to.
 *
 * A node to represent the object will have already been created and is
 * available to store things in, this node will be automatically saved after all
 * implementations of this hook are called. You should not call node_save()
 * manually on this node.
 *
 * If you implement hook_hosting_TASK_OBJECT_context_options() then you will
 * probably want to implement this hook also, as they mirror each other.
 *
 * @param object $context
 *   The backend context that is being imported.
 * @param object $node
 *   The node object that is being built up from the $context. You should modify
 *   the fields and properties so that they reflect the contents of the
 *   $context.
 *
 * @see hosting_drush_import()
 * @see hook_hosting_TASK_OBJECT_context_options()
 */
function hook_drush_context_import($context, &$node) {
  // From hosting_alias_drush_context_import().
  if ($context->type == 'site') {
    $node->aliases = $context->aliases;
    $node->redirection = $context->redirection;
  }
}

/**
 * Register a hosting feature with Aegir.
 *
 * The frontend provides a UI for enabling and disabling features, which usually
 * corresponds to enabling and disabling a module providing the feature.
 *
 * Features are declared in YAML files named:
 * hosting.feature.FEATURE_KEY.yml
 *
 * Note that the module providing this hook does not need to be enabled for it
 * to be called. The frontend will use details in this hook to enable a module
 * if the feature is enabled.
 *
 * Features are now declared in `hosting.feature.*.yml` files stored alongside
 * each module. Example (hosting.feature.example.yml):
 *
 * @code
 * example:
 *   title: 'Example feature'
 *   description: 'Example feature documenting how to create extensions.'
 *   status: disabled
 *   module: hosting_example
 *   entity_type: hosting_example
 *   enable: 'hosting_example_feature_enable_callback'
 *   disable: 'hosting_example_feature_disable_callback'
 *   group: experimental
 * @endcode
 *
 * @deprecated Hook-based feature definitions are no longer loaded.
 *
 * @see hosting_get_features()
 */
function hook_hosting_feature() {
  return [];
}

/**
 * Define hosting queues.
 *
 * @return array
 *   An array with the queue specification. @see hosting_get_queues for an example
 */
function hook_hosting_queues() {

}

/**
 * Alter module defined queue definitions before they are processed.
 *
 * This hook is invoked before hosting calculates the number of items to
 * process when processing queues, so, for example, you could alter the number of apparent
 * items in the queue.
 *
 * @param array $queues
 *   The array of queue definitions of queues provided by modules.
 *
 * @see hosting_get_queues
 * @see hook_hosting_queues
 * @see hook_hosting_processed_queues_alter
 */
function hook_hosting_queues_alter(&$queues) {
  if (isset($queues['cron'])) {
    // Do not execute the cron queue at weekends.
    if (date('N', REQUEST_TIME) > 5) {
      $queues['cron']['total_items'] = 0;
    }
  }
}

/**
 * Alter module defined queue definitions after they are processed.
 *
 * This hook is invoked after hosting module calculates the number of items to
 * process when processing queues, and after the configurable information has
 * been merged in.
 *
 * @param array $queues
 *   The processed array of queue definitions of queues provided by modules.
 *
 * @see hosting_get_queues
 * @see hook_hosting_queues
 * @see hook_hosting_queues_alter
 */
function hook_hosting_processed_queues_alter(&$queues) {
  if (isset($queues['cron'])) {
    // Force the cron queue to always be disabled.
    $queues['cron']['enabled'] = FALSE;
  }
}

/**
 * Add or change context options before a hosting task runs.
 *
 * This hook is invoked just before any task that has the 'provision_save' flag
 * equal to TRUE. These include the 'install', 'verify' and 'import' tasks.
 *
 * The TASK_OBJECT will be either: 'server', 'platform' or 'site'.
 *
 * This gives other modules the chance to send data to the backend to be
 * persisted by services there. The entire task is sent so that you have access
 * to it, but you should avoid changing things outside of the
 * $task->content_options collection.
 *
 * If you are sending extra context options to the backend based on properties
 * in the object's node, then you should also implement the
 * hook_drush_context_import() hook to re-create those properties on the node
 * when a context is imported.
 *
 * @param object $task
 *   The hosting task that is about to be executed, the task is passed by
 *   reference. The context_options property of this object is about to be saved
 *   to the backend, so you can make any changes before that happens. Note that
 *   these changes won't persist in the backend unless you have a service that
 *   will store them.
 *
 *   The node representing the object of the task, e.g. the site that is being
 *   verified is available in the $task->ref property.
 *
 * @see hosting_task_execute_current()
 * @see hook_drush_context_import()
 * @see hook_hosting_tasks()
 */
function hook_hosting_TASK_OBJECT_context_options(&$task) {
  // From hosting_hosting_platform_context_options().
  $task->context_options['server'] = '@server_master';
  $task->context_options['web_server'] = hosting_context_name($task->ref->web_server);
}

/**
 * Perform actions when a task has failed and has been rolled back.
 *
 * Replace TASK_TYPE with the type of task that if rolled back you will be
 * notified of.
 *
 * @param object $task
 *   The hosting task that has failed and has been rolled back.
 * @param array $data
 *   An associative array of the backend task output. The array should contain
 *   at least the following:
 *   - "output": The raw output from the backend command executed.
 *   - "error_status": The error status of the command run on the backend.
 *   - "log": The log messages.
 *   - "error_log": The list of errors that occurred when running the command.
 *   - "context": The options for the backend command, this may contain
 *     options that were set when the command ran, or options that were set by
 *     the command itself.
 *
 * @see hosting_task_rollback_current()
 */
function hook_hosting_TASK_TYPE_task_rollback($task, $data) {
  // From hosting_site_hosting_install_task_rollback().

  // @TODO : we need to check the returned list of errors, not the code.
  if (hosting_output_has_error_code($data, 'PROVISION_DRUPAL_SITE_INSTALLED')) {
    // Site has already been installed. Try to import instead.
    hosting_log(t('This site appears to be installed already. Generating an import task.'));
    hosting_add_task($task->rid, 'import');
  }
  else {
    $task->ref->no_verify = TRUE;
    $task->ref->site_status = HOSTING_SITE_DISABLED;
    if ($task->ref instanceof \Drupal\Core\Entity\EntityInterface) {
      $task->ref->save();
    }
  }
}

/**
 * Act on hosting entities defined by other modules.
 *
 * This is a more specific version of hook_entity_*() that includes an entity
 * type and operation in the function name. When implementing this hook you
 * should replace ENTITY_TYPE with the hosting entity type and OP with the
 * operation you would like to be notified for (insert, update, delete, etc).
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity the action is being performed on.
 *
 * @see hook_entity_insert()
 * @see hook_entity_update()
 * @see hook_entity_delete()
 */
function hook_hosting_client_delete(\Drupal\Core\Entity\EntityInterface $entity) {
  // From hosting_client_entity_delete().
  \Drupal::database()->delete('hosting_client')->condition('id', $entity->id())->execute();
}

/**
 * Act after the hostmaster site has been installed and registered.
 *
 * This hook runs once after the hostmaster site exists. It is invoked during
 * hosting module install.
 *
 * @param \Drupal\Core\Entity\EntityInterface $hostmaster
 *   The hostmaster hosting_site entity.
 */
function hook_hosting_post_install(\Drupal\Core\Entity\EntityInterface $hostmaster) {
  // Example: create a default server or seed config based on hostmaster.
}

/**
 * Perform actions when a task has completed succesfully.
 *
 * Replace TASK_TYPE with the type of task that if completed you will be
 * notified of. This is a good place to hook in and record changes in the
 * frontend as a result of the task executing in the backend. If you just want
 * to hook into the backend then you probably want to consider using the
 * 'standard' Drush hooks there, i.e. host_post_provision_verify(),
 * hook_post_provision_install() etc.
 *
 * @param object $task
 *   The hosting task that has completed.
 * @param array $data
 *   An associative array of the backend task output. The array should contain
 *   at least the following:
 *   - "output": The raw output from the backend command executed.
 *   - "error_status": The error status of the command run on the backend,
 *     should be 0 normally.
 *   - "log": The log messages.
 *   - "error_log": The list of errors that occurred when running the command.
 *   - "context": The options for the backend command, this may contain
 *     options that were set when the command ran, or options that were set by
 *     the command itself.
 *
 * @see hosting_post_task_current()
 */
function hook_post_hosting_TASK_TYPE_task($task, $data) {
  // From hosting_site_post_hosting_backup_task().
  if ($data['context']['backup_file'] && $task->ref->type == 'site') {
    $platform = \Drupal::entityTypeManager()->getStorage('hosting_platform')->load($task->ref->get('platform')->target_id);

    $desc = $task->task_args['description'];
    $desc = ($desc) ? $desc : t('Generated on request');
    $web_server_id = $platform instanceof \Drupal\Core\Entity\ContentEntityInterface ? $platform->get('web_server')->target_id : NULL;
    hosting_site_add_backup($task->ref->id(), $web_server_id, $data['context']['backup_file'], $desc, $data['context']['backup_file_size']);
  }
}

/**
 * Process the specified queue.
 *
 * Modules providing a queue should implement this function an process the
 * number of items from the queue that are specified. It is up the to the
 * module to determine which items it wishes to process.
 *
 * If you wish to process multiple items at the same time you will need to fork
 * the process by calling hosting_run_drush_command() with the 'fork' option,
 * specifying a command with the arguments required to process your task.
 * Otherwise you can do all your processing in this function, or similarly call
 * hosting_run_drush_command() without the 'fork' option.
 *
 * @param int $count
 *   The maximum number of items to process.
 *
 * @see hosting_run_queue()
 * @see hosting_get_queues()
 */
function hosting_QUEUE_TYPE_queue($count = 5) {
  // From hosting_tasks_queue().
  global $provision_errors;

  hosting_log(t('Running tasks queue'));
  $tasks = hosting_get_new_tasks($count);
  foreach ($tasks as $task) {
    hosting_run_drush_command('@self', 'hosting-task', array($task->id()), array(), array('fork' => TRUE));
  }
}

/**
 * @see hosting_queues()
 */
function hosting_TASK_SINGULAR_list() {

}

/**
 * @return string
 * @see hosting_queue_block()
 */
function hosting_TASK_SINGULAR_summary() {

}

/**
 * Reacts any time a task has it's status updated, including when being run in
 * the queue, ending, or being cancelled.
 *
 * @param object $task
 *   The task that has just completed.
 *   Note that $task->task_status has the old value, $status has the new value.
 *   The database will just have been updated before this hook is called.
 * @param int $status
 *   The new status of that task. Can be HOSTING_TASK_SUCCESS, etc.
 */
function hook_hosting_task_update_status($task, $status) {

  // A task's "RID" is the entity ID for the object the task is run on. (Site, Platform, Server, etc)
  $node = hosting_entity_load_any($task->rid);

  // On error, output a new message.
  if ($status == HOSTING_TASK_ERROR) {
    $label = hosting_entity_label($node) ?? '';
    hosting_log(t('@title: @task task ended in an error', [
      '@task' => $task->task_type,
      '@title' => $label,
    ]), 'error');
  }
  else {
    $label = hosting_entity_label($node) ?? '';
    hosting_log(t('Task completed successfully: @task', ['@task' => $task->task_type]), 'ok');
    hosting_log(t('@title: @task task ended with @status', [
      '@task' => $task->task_type,
      '@title' => $label,
      '@status' => _hosting_parse_error_code($status),
    ]), 'ok');
  }
}

/**
 * Return a list of Aegir entities to guard against destructive tasks.
 *
 * @see: hook_hosting_task_dangerous_tasks().
 * @see: hook_hosting_task_guarded_nodes_alter();
 */
function hook_hosting_task_guarded_nodes() {
  // Guard against destructive tasks run on the hostmaster site or platform.
  $hostmaster_site_id = hosting_get_hostmaster_site_id();
  $hostmaster_platform_id = hosting_get_hostmaster_platform_id();
  $guarded_ids = array(
    $hostmaster_site_id,
    $hostmaster_platform_id,
  );
  return $guarded_ids;
}

/**
 * Alter the list of guarded nodes.
 *
 * @param $ids
 *   A list of entity IDs as returned by hook_hosting_task_guarded_nodes().
 */
function hook_hosting_task_guarded_nodes_alter(&$ids) {}

/**
 * Return a list of dangerous tasks.
 *
 * These tasks will be blocked on guarded entities.
 * @see: hook_hosting_task_guarded_nodes().
 * @see: hook_hosting_task_dangerous_tasks_alter().
 */
function hook_hosting_task_dangerous_tasks() {
  $dangerous_tasks = array(
    'disable',
    'delete',
  );
  return $dangerous_tasks;
}

/**
 * Alter the list of dangerous tasks.
 *
 * @param $nids
 *   A list of tasks as returned by hook_hosting_task_dangerous_tasks().
 */
function hook_hosting_task_dangerous_tasks_alter(&$tasks) {}

/**
 * @} End of "addtogroup hostinghooks".
 */

/**
 * Easily create a new site and a platform by passing a name and publish path.
 *
 * To get a new website running from a new codebase in Aegir, you need to create
 * a platform, and then a site node. With the latest version, you can use code
 * to do both at once.
 *
 * This function will create a platform entity pointing at an existing
 * Composer-managed codebase, and then create a site entity with the URL
 * $name.mywebservice.com.
 */
function example_create_site($name, $publish_path) {

  // Create site node.
  $site_values = array(
    'label' => "{$name}.mywebservice.com",
    'status' => 1,
    'client' => HOSTING_DEFAULT_CLIENT,
  );

  // Create a platform node.
  $platform = \Drupal::entityTypeManager()->getStorage('hosting_platform')->create(array(
    'label' => "mywebservice_{$name}",
    'publish_path' => $publish_path,
    'status' => HOSTING_PLATFORM_QUEUED,
  ));
  $platform->save();

  // Attach platform to site entity.
  $site_values['platform'] = $platform->id();
  $site_values['status'] = HOSTING_SITE_QUEUED;

  $site = \Drupal::entityTypeManager()->getStorage('hosting_site')->create($site_values);
  $site->save();
}

/**
 * @} End of "addtogroup hostinghooks".
 */

/**
 * @defgroup taskhooks Task Execution Context
 * @{
 *
 * Task execution context and hooks.
 *
 * As of Aegir 4.x (Drupal 11, PHP 8.3+), task execution uses a dedicated
 * TaskExecutionContext class instead of dynamic properties on task entities.
 * This improves compatibility with PHP 8.2+ which deprecated dynamic properties.
 *
 * When implementing hooks that interact with tasks during execution, you will
 * receive a TaskExecutionContext object instead of the task entity directly.
 *
 * @section accessing_context Accessing the Task Execution Context
 *
 * @code
 * // Get the current task execution context
 * $context = hosting_task_get_current();
 *
 * // Access the task entity
 * $task = $context->getTask();
 *
 * // Access the reference entity (site, platform, server, etc.)
 * $ref = $context->getRef();
 *
 * // Get or set task arguments
 * $args = $context->getArgs();
 * $args[2] = '@example.com';
 * $context->setArgs($args);
 *
 * // Get or set task options
 * $options = $context->getOptions();
 * $options['profile'] = 'standard';
 * $context->setOptions($options);
 *
 * // Get or set context options (for provision backend)
 * $context_options = $context->getContextOptions();
 * $context_options['uri'] = 'example.com';
 * $context->setContextOptions($context_options);
 * @endcode
 *
 * @section hooks_with_context Hooks that receive TaskExecutionContext
 *
 * The following hooks now receive a TaskExecutionContext object:
 * - hook_hosting_TASK_TYPE_task_rollback($context, $data)
 * - hook_post_hosting_TASK_TYPE_task($context, $data)
 * - hook_hosting_TASK_OBJECT_context_options(&$context)
 *
 * @section migrating_code Migrating Existing Code
 *
 * Old code that accessed dynamic properties:
 * @code
 * function mymodule_hosting_migrate_task_rollback($task, $data) {
 *   $task->args[2] = '@example.com';
 *   $task->options['profile'] = 'standard';
 *   $site = $task->ref;
 * }
 * @endcode
 *
 * New code using TaskExecutionContext:
 * @code
 * function mymodule_hosting_migrate_task_rollback($context, $data) {
 *   $args = $context->getArgs();
 *   $args[2] = '@example.com';
 *   $context->setArgs($args);
 *
 *   $options = $context->getOptions();
 *   $options['profile'] = 'standard';
 *   $context->setOptions($options);
 *
 *   $site = $context->getRef();
 * }
 * @endcode
 *
 * @see \Drupal\hosting\TaskExecutionContext
 * @see hosting_task_get_current()
 * @see hosting_task_set_current()
 *
 * @} End of "addtogroup taskhooks".
 */
