<?php

/**
 * @file
 * Drush include for the Hosting module's hosting task command.
 */

/**
 * Validate hook for the hosting-task Drush command.
 *
 * We do some magic in this command to allow the user to run either a specifc
 * task by specifying a node id or chosen task type by specifying the type of
 * task, e.g. 'verify' or 'migrate'.
 *
 * @see hosting_task_execute_current()
 */
function hosting_task_validate($id, $type = NULL, array $task_args = [], array $options = []) {
  hosting_task_clear_errors();
  hosting_task_set_options($options);
  hosting_task_set_output([]);

  if (\Drupal::hasService('account_switcher')) {
    $user = \Drupal::entityTypeManager()->getStorage('user')->load(1);
    if ($user) {
      \Drupal::service('account_switcher')->switchTo($user);
    }
  }

  if (is_numeric($id)) {
    $task = hosting_task_load_entity($id);
  }
  elseif (is_string($id) && isset($type)) {
    $ref = hosting_context_load($id);
    $ref_id = hosting_entity_id($ref);
    if ($ref_id) {

      // Get additional arguments to the drush command and convert them to "task_args" as expected by the task node.
      $parsed_args = [];
      foreach ($task_args as $arg) {
        if (strpos($arg, '=') === FALSE) {
          continue;
        }
        [$name, $value] = explode('=', $arg, 2);
        $parsed_args[$name] = $value;
      }
      $task = hosting_add_task($ref, $type, $parsed_args);
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
        if (!hosting_platform_validate_runtime($task->ref)) {
          $message = hosting_task_get_last_error() ?? t('Platform validation failed.');
          throw new \Drush\Exceptions\UserAbortException((string) $message);
        }
      }
    }
    /* if not already running, remove the task from the queue
     * this is to avoid concurrent task runs */
    if ((int) $task->get('task_status')->value == HOSTING_TASK_PROCESSING && !hosting_task_get_option('force', FALSE)) {
      throw new \Drush\Exceptions\UserAbortException((string) t('This task is already running, use --force.'));
    }
    if ((int) $task->get('task_status')->value != HOSTING_TASK_QUEUED && !hosting_task_get_option('force', FALSE)) {
      throw new \Drush\Exceptions\UserAbortException((string) t('This task is not queued, use --force.'));
    }
    $task->set('task_status', HOSTING_TASK_PROCESSING);
    $task->save();
    hosting_task_set_current($task);
    $message = t('Task starts processing: @label', ['@label' => $task->label()]);
    hosting_task_log($task->id(), 'queue', (string) $message);
    hosting_log($message, 'queue', [], 'hosting_task');

    // Load Task Info.
    $tasks_info = hosting_available_tasks($ref_type);

    // Find task type and pass through if it needs provision_save.
    if (isset($tasks_info[$task->get('task_type')->value])) {
      $task->task_info = $tasks_info[$task->get('task_type')->value];
    }
  }
  else {
    throw new \Drush\Exceptions\UserAbortException((string) t("Could not find or create a '!type' task for hosting context '!context'.", array(
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
 * @see hosting_task_validate()
 * @see hook_hosting_TASK_OBJECT_context_options()
 */
function hosting_task_execute_current() {
  $task = hosting_task_get_current();
  if (!$task instanceof \Drupal\Core\Entity\EntityInterface) {
    throw new \Drush\Exceptions\UserAbortException('No hosting task context is available.');
  }
  $output = array();
  $mode = hosting_task_get_option('debug', FALSE) ? 'GET' : 'POST';
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
    foreach (\Drupal::moduleHandler()->getImplementations($hook) as $module) {
      $function = $module . '_' . $hook;
      call_user_func_array($function, array(&$task));
    }

    hosting_run_drush_command('@none', 'provision-save', array('@' . $task->ref->hosting_name), $task->context_options, array('method' => $mode, 'integrate' => TRUE));
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
  hosting_task_set_output($output);

  $has_error = !empty($output['error_status']);
  if ($has_error) {
    $message = t('Task execution failed with status @status.', ['@status' => $output['error_status']]);
    hosting_task_set_error('HOSTING_TASK_FAILED', (string) $message);
    hosting_log($message, 'error', [], 'hosting_task');
    if ($task->id()) {
      hosting_task_log($task->id(), 'error', (string) $message);
    }
  }

  // On succesful delete, remove the named context.
  if ($task->get('task_type')->value === 'delete' && !$has_error) {
    hosting_run_drush_command('@none', 'provision-save', array('@' . $task->ref->hosting_name), array('delete' => TRUE), array('method' => $mode, 'integrate' => TRUE));
  }

  $task->set('delta', microtime(TRUE) - $task->get('executed')->value);
  $task->save();

  if ($has_error) {
    throw new \Drush\Exceptions\UserAbortException((string) $message);
  }
}

/**
 * Rollback hook for the hosting-task Drush command.
 *
 * @see hook_hosting_TASK_TYPE_task_rollback()
 */
function hosting_task_rollback_current() {
  $task = hosting_task_get_current();
  if (!$task instanceof \Drupal\Core\Entity\EntityInterface) {
    return;
  }
  $hook = sprintf("hosting_%s_task_rollback", str_replace('-', '_', $task->get('task_type')->value));
  $message = t('Invoking @hook hooks.', ['@hook' => $hook]);
  hosting_log($message, 'notice', [], 'hosting_task');
  if ($task->id()) {
    hosting_task_log($task->id(), 'notice', (string) $message);
  }
  \Drupal::moduleHandler()->invokeAll($hook, [$task, hosting_task_get_output()]);
}

/**
 * Post completion hook for the hosting-task Drush command.
 *
 * @see hook_post_hosting_TASK_TYPE_task()
 */
function hosting_post_task_current($task) {
  $task = hosting_task_get_current();
  if (!$task instanceof \Drupal\Core\Entity\EntityInterface) {
    return;
  }

  $hook = sprintf("post_hosting_%s_task", str_replace('-', '_', $task->get('task_type')->value));
  $message = t('Invoking @hook hooks.', ['@hook' => $hook]);
  hosting_log($message, 'notice', [], 'hosting_task');
  if ($task->id()) {
    hosting_task_log($task->id(), 'notice', (string) $message);
  }
  \Drupal::moduleHandler()->invokeAll($hook, [$task, hosting_task_get_output()]);
}
