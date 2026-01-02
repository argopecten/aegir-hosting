<?php

/**
 * @file
 * Drush include for the Hosting module's hosting task command.
 */

/**
 * Log a message to the current task's node if possible, the screen otherwise.
 */
function _hosting_task_log($entry) {
  $task = drush_get_context('HOSTING_TASK');
  $task_id = $task instanceof \Drupal\Core\Entity\EntityInterface ? $task->id() : NULL;
  if ($task_id) {
    hosting_task_log($task_id, $entry['type'], $entry['message'], isset($entry['error']) ? $entry['error'] : '', $entry['timestamp']);
  }
  else {
    _hosting_task_log_print($entry);
  }
  if (drush_get_option('debug', FALSE)) {
    _hosting_task_log_print($entry);
  }
}

/**
 * Switch between Drush 8's OO logging and the older private function.
 */
function _hosting_task_log_print($entry) {
  if (function_exists('_drush_print_log')) {
    // Remove after dropping Drush 6 comaptibility.
    return _drush_print_log($entry);
  }
  else {
    $logger = new Drush\Log\Logger();
    return $logger->log($entry['type'], $entry['message'], $entry);
  }
}

/**
 * Validate hook for the hosting-task Drush command.
 *
 * We do some magic in this command to allow the user to run either a specifc
 * task by specifying a node id or chosen task type by specifying the type of
 * task, e.g. 'verify' or 'migrate'.
 *
 * @see drush_hosting_task()
 */
function drush_hosting_task_validate($id, $type = NULL) {
  drush_set_option('user', 1);
  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_LOGIN);
  if (is_numeric($id)) {
    $task = hosting_task_load_entity($id);
  }
  elseif (is_string($id) && isset($type)) {
    $ref = hosting_context_load($id);
    $ref_id = hosting_entity_id($ref);
    if ($ref_id) {

      // Get additional arguments to the drush command and convert them to "task_args" as expected by the task node.
      $task_args = array();

      // Parse task_arguments passed to drush in the format "name=value"
      // phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
      $arguments = func_get_args();
      $drush_args = array_splice($arguments, 2);
      foreach ($drush_args as $i => $arg) {
        list($name, $value) = explode('=', $arg);
        $task_args[$name] = $value;
      }
      $task = hosting_add_task($ref, $type, $task_args);
    }
  }

  if ($task instanceof \Drupal\Core\Entity\EntityInterface && $task->getEntityTypeId() === 'hosting_task') {
    $task = hosting_task_load_arguments($task);
    $task->ref = hosting_entity_load_from_context_id($task->get('rid')->value);
    $task->options = array();
    $ref_type = hosting_entity_task_scope($task->ref);
    $task->context_options = array(
      'context_type' => $ref_type,
      'master_url' => \Drupal\Core\Url::fromRoute('<front>', array('absolute' => TRUE))->toString(),
      'root' => NULL,
      'uri' => NULL,
    );
    $task->args = array();
    $task->set('changed', REQUEST_TIME);
    $task->set('executed', REQUEST_TIME);
    if ($task->ref instanceof \Drupal\Core\Entity\ContentEntityInterface) {
      if ($task->ref->getEntityTypeId() === 'hosting_platform' && $task->get('task_type')->value === 'verify') {
        hosting_platform_validate_runtime($task->ref);
        if (drush_get_error()) {
          return;
        }
      }
    }
    /* if not already running, remove the task from the queue
     * this is to avoid concurrent task runs */
    if ((int) $task->get('task_status')->value == HOSTING_TASK_PROCESSING && !drush_get_option('force', FALSE)) {
      return drush_set_error('HOSTING_TASK_RUNNING', dt("This task is already running, use --force"));
    }
    if ((int) $task->get('task_status')->value != HOSTING_TASK_QUEUED && !drush_get_option('force', FALSE)) {
      return drush_set_error('HOSTING_TASK_NOT_QUEUED', dt("This task is not queued, use --force"));
    }
    $task->set('task_status', HOSTING_TASK_PROCESSING);
    $task->save();
    drush_set_context('HOSTING_TASK', $task);
    drush_set_context('DRUSH_LOG_CALLBACK', '_hosting_task_log');
    drush_log(dt("Task starts processing") . ': ' . $task->label(), 'queue');

    // Load Task Info.
    $tasks_info = hosting_available_tasks($ref_type);

    // Find task type and pass through if it needs provision_save.
    if (isset($tasks_info[$task->get('task_type')->value])) {
      $task->task_info = $tasks_info[$task->get('task_type')->value];
    }
  }
  else {
    drush_set_error('HOSTING_INVALID_TASK', t("Could not find or create a '!type' task for hosting context '!context'.", array(
      '!type' => $type,
      '!context' => $id,
    )));
  }
}

/**
 * Drush hosting task command.
 *
 * This is the main way that the frontend communicates with the backend. Tasks
 * correspond to backend drush commands, and the results and log of the command
 * are attached to the task for reference.
 *
 * @see drush_hosting_task_validate()
 * @see hook_hosting_TASK_OBJECT_context_options()
 */
function drush_hosting_task() {
  $task = &drush_get_context('HOSTING_TASK');
  $output = array();
  $mode = drush_get_option('debug', FALSE) ? 'GET' : 'POST';
  $ref_type = hosting_entity_task_scope($task->ref);
  $ref_status = hosting_entity_status($task->ref, $ref_type);

  // Make sure argument order is correct.
  ksort($task->args);

  // If this task type needs it, run provision-save to create the named context.
  if (!empty($task->task_info['provision_save'])) {
    // Invoke hook_hosting_TASK_OBJECT_context_options()
    // We copy module_invoke_all() here because it doesn't pass by
    // reference and it breaks under PHP 5.3
    $hook = 'hosting_' . hosting_entity_task_scope($task->ref) . '_context_options';
    foreach (module_implements($hook) as $module) {
      $function = $module . '_' . $hook;
      call_user_func_array($function, array(&$task));
    }

    drush_invoke_process('@none', 'provision-save', array('@' . $task->ref->hosting_name), $task->context_options, array('method' => $mode, 'integrate' => TRUE));
  }

  if (($ref_type == 'site' && $ref_status == HOSTING_SITE_DELETED)
    || ($ref_type == 'platform' && $ref_status == HOSTING_PLATFORM_DELETED)) {

    // We're performing a task on a site that has been deleted...
    // d() will not be returning a site object.
    $alias = '@none';
  }
  else {
    $alias = $task->ref->hosting_name;
  }

  if (!isset($task->task_command)) {
    $task->task_command = 'provision-' . $task->get('task_type')->value;
  }
  // Run the actual command. Adding alias here to work around Drush API.
  $output = provision_backend_invoke($alias, $task->task_command, $task->args, $task->options, $mode);
  // Pass the drush output back to the HOOK_post_hosting_TASK_task() and
  // HOOK_hosting_TASK_task_rollback() hooks, as the $data argument.
  drush_set_context('HOSTING_DRUSH_OUTPUT', $output);

  // On succesful delete, remove the named context.
  if ($task->get('task_type')->value === 'delete' && !drush_get_error()) {
    drush_invoke_process('@none', 'provision-save', array('@' . $task->ref->hosting_name), array('delete' => TRUE), array('method' => $mode, 'integrate' => TRUE));
  }

  $task->set('delta', microtime(TRUE) - $task->get('executed')->value);
  $task->save();
}

/**
 * Rollback hook for the hosting-task Drush command.
 *
 * @see hook_hosting_TASK_TYPE_task_rollback()
 */
function drush_hosting_task_rollback() {
  $task = &drush_get_context('HOSTING_TASK');
  $hook = sprintf("hosting_%s_task_rollback", str_replace('-', '_', $task->get('task_type')->value));
  drush_log(dt('Invoking :hook hooks.', array(':hook' => $hook)));
  module_invoke_all($hook, $task, drush_get_context('HOSTING_DRUSH_OUTPUT'));
}

/**
 * Post completion hook for the hosting-task Drush command.
 *
 * @see hook_post_hosting_TASK_TYPE_task()
 */
function drush_hosting_post_hosting_task($task) {
  $task = &drush_get_context('HOSTING_TASK');

  $hook = sprintf("post_hosting_%s_task", str_replace('-', '_', $task->get('task_type')->value));
  drush_log(dt('Invoking :hook hooks.', array(':hook' => $hook)));
  module_invoke_all($hook, $task, drush_get_context('HOSTING_DRUSH_OUTPUT'));
}
