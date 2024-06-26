<?php

/**
 * @file
 * Dispatcher daemon
 *
 * This file is the heart of the dispatcher drush command. It
 * implements most of the backend functionality.
 */

// This is necessary for signal handling to work
declare (ticks = 1);

/**
 * Implements hook_drush_command().
 */
function hosting_queued_drush_command() {
  $items = array();

  $items['hosting-queued'] = array(
    'description' => 'Runs the tasks queue',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'drupal dependencies' => array(
      'hosting_queued',
    ),
  );

  $items['hosting-release-lock'] = array(
    'description' => 'Releases any stale locks on the tasks queue',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'drupal dependencies' => array(
      'hosting_queued',
    ),
  );

  return $items;
}

/**
 * Drush command to release the lock on the task queue.
 */
function drush_hosting_queued_hosting_release_lock() {
  drush_log('Clearing any stale locks on task queue.');
  $name = 'hosting_queue_tasks_running';
  global $locks;
  unset($locks[$name]);
  db_delete('semaphore')
    ->condition('name', $name)
    ->execute();
}

/**
 * Drush command to execute hosting tasks.
 */
function drush_hosting_queued() {
  if (function_exists('pcntl_signal')) {
    // reload the server on SIGHUP
    pcntl_signal(SIGHUP, 'hosting_queued_restart');
    pcntl_signal(SIGINT, 'hosting_queued_stop');
    pcntl_signal(SIGTERM, 'hosting_queued_stop');
  }

  // Set a nice high time limit, if we can:
  if (function_exists('set_time_limit')) {
    @set_time_limit(0);
  }

  // in some environments (e.g. in "productin") ENV is not actually
  // set (!) so try to guess from $_SERVER
  if (strpos(ini_get('variables_order'), 'E') === FALSE) {
    if (strpos(ini_get('variables_order'), 'S') === FALSE) {
      drush_log(dt('Neither $_ENV nor $_SERVER are available to set up proper environment inheritance; ensure E and/or S is set in your php.ini\'s "variables_order" setting.'), 'warning');
    }
    else {
      $_ENV = $_SERVER;
    }
  }

  $end_time = variable_get('hosting_queued_process_lifetime', 3600)  + REQUEST_TIME;

  // Record the fact that we're running, so we can give some feedback in the
  // frontend.
  variable_set('hosting_queued_process_started', REQUEST_TIME);

  // Check if hosting queue is paused, show appropriate message.
  $is_paused = variable_get('hosting_queued_paused', 0);
  if ($is_paused) {
    watchdog('hosting_queued', 'Started Hosting queue daemon, hosting queue is paused.');
    drush_log('Started hosting queue daemon, hosting queue is paused.', 'ok');
  }
  else {
    watchdog('hosting_queued', 'Started Hosting queue daemon, waiting for new tasks');
    drush_log('Started hosting queue daemon. Waiting for new tasks.', 'ok');
  }

  global $conf;

  while (TRUE) {

    // Detect if the hosting queue is paused or not.
    // Reload variables. Since this is still a single request, any variable changes are not available.
    $conf = variable_initialize();
    if (variable_get('hosting_queued_paused', 0)) {

      // If was not paused on last cycle, announce that it has been paused.
      if (!$is_paused) {
        drush_log('Hosting Queued has been paused.', 'ok');
      }
      sleep(1);

      // Save current state for next cycle.
      $is_paused = variable_get('hosting_queued_paused', 0);

      continue;
    }

    // If was paused on last cycle, announce that it has been unpaused.
    if ($is_paused) {
      drush_log('Hosting Queued has been unpaused.', 'ok');
    }

    // Save current state for next cycle.
    $is_paused = variable_get('hosting_queued_paused', 0);

    try {
      // Should we terminate.
      if (time() > $end_time) {
        // Restart the daemon to recycle leaked memory.
        hosting_queued_restart();
      }


    // Get some tasks to run.
    if ($tasks = @hosting_get_new_tasks(1)) {

      drush_log(dt("Found %count tasks in queue. Running...", array(
        '%count' => count($tasks),
      )), "notice");

      if (lock_acquire('hosting_queue_tasks_running', HOSTING_QUEUE_LOCK_TIMEOUT)) {
        drush_log('Acquired lock on task queue.');
        foreach ($tasks as $task) {
          // We sleep for a second just in case others want to run the task first.
          // This guards against other processes that want to add a hosting task
          // with arguments and run it immediately, they should be able to do this
          // without us getting in there first.
          // This is a workaround for http://drupal.org/node/1003536
          drush_log(dt('Found task to execute. Pausing before execution.'));
          sleep(1);

          // Execute the task.
          hosting_task_execute($task, array('interactive' => TRUE));

          // Delay for a configurable amount of time.
          $delay = variable_get('hosting_queued_post_task_delay', 0);
          if (!empty($delay)) {
            drush_log(dt('Going to sleep for @count seconds after completing task.', array('@count' => $delay)));
            sleep($delay);
          }

          // We're done with this task, this unset might help reduce memory usage.
          unset($task);

          // Should we terminate.
          if (REQUEST_TIME > $end_time) {
            // Restart the daemon to recycle leaked memory
            hosting_queued_restart();
          }
        }
        drush_log('Releasing lock on task queue.');
        lock_release('hosting_queue_tasks_running');
      }
    }

    }
    catch (Exception $e) {
      // Check if there was a database error, so we can recover gracefully if needed.
      // See: https://drupal.org/node/1992254.
      drush_log('Caught database error.', 'warning');
      $db = drush_convert_db_from_db_url($GLOBALS['db_url']);
      drush_log('Waiting for database to become available.', 'warning');
      $timeout = 120;
      do {
        // Give the database time to come back.
        sleep(1);
        $timeout--;
        // Check connection
        $connect = FALSE;
        try {
          $connect = Database::getConnection();
        }
        catch (Exception $e) {
          $connect = FALSE;
        }
        drush_log('.', 'warning');
      } while (!$connect && $timeout > 0);
      // Close connection
      db_close();
      drush_log('Restarting queue daemon.', 'warning');
      hosting_queued_restart();
    }

    // Wait here only if we didn't process tasks,
    // either because none were available or lock_acquire failed.
    // This is to avoid an infinite loop if there are no tasks in the queue.
    sleep(1);

    if (drush_get_option('onetime')) {
      drush_log(dt("exiting after processing all tasks, as requested by --onetime"));
      break;
    }
    unset($tasks);
  }
}

/**
 * Handle interruption signals gracefully
 *
 * We do not want to interrupt children tasks, so we wait for them
 * before stopping.
 */
function hosting_queued_stop($signal) {
  watchdog('hosting_queued', 'Received signal @signal, waiting for children to die.', array('@signal' => $signal));
  $status = NULL;
  pcntl_wait($status);
  drush_log('Releasing lock on task queue.');
  lock_release('hosting_queue_tasks_running');
  watchdog('hosting_queued', 'Stopped daemon');
  exit($status);
}

/**
 * Restart the dispatcher to work around memory leaks
 */
function hosting_queued_restart($signal = NULL) {
  try {
    // If we received a singal, process it.
    if (!is_null($signal)) {
      watchdog('hosting_queued', 'Received signal @signal, waiting for children to die.', array('@signal' => $signal));
      $status = NULL;
      pcntl_wait($status);
    }

    // We need the PCNTL extension to be able to auto restart.
    if (function_exists('pcntl_exec')) {
      $args = $_ENV['argv'];
      $drush = array_shift($args);

      // Strip sub-array to avoid warning "Array to string conversion"
      unset($_ENV['argv']);

      watchdog('hosting_queued', 'Restarting queue daemon with @drush @args.', array('@drush' => $drush, '@args' => implode(" ", $args)));
      drush_log('Releasing lock on task queue.');
      lock_release('hosting_queue_tasks_running');
      // close all open database file descriptors
      hosting_queued_db_close_all();
    }
    else {
      watchdog('hosting_queued', 'PCNTL not installed, unable to auto-restart.', array(), WATCHDOG_WARNING);
    }
  }
  catch (Exception $e) {
    // Caught ... dropping.
  }

  // New try block, to still restart if e.g. the watchog log ging faild on a missing DB connection.
  try {
    if (function_exists('pcntl_exec')) {
      pcntl_exec($drush, $args, $_ENV);
      drush_dog('Could not restart the queue daemon, aborting.', 'error');
      /* NOTREACHED */
    }
  }
  catch (Exception $e) {
    // Caught ... dropping.
  }

  drush_log('Releasing lock on task queue.');
  lock_release('hosting_queue_tasks_running');

  // Explicit exit in case we're handling a signal
  exit(1);
}

/**
 * Close all database file descriptors, as exec() doesn't close them.
 */
function hosting_queued_db_close_all() {
  global $databases;
  foreach (array_keys($databases) as $target) {
    foreach (array_keys($databases[$target]) as $key) {
      Database::closeConnection($target, $key);
    }
  }
}

/**
 * Get the proper way to call drush again.
 *
 * Note that unlike drush_find_drush() we return an array of parts, and we trim
 * the 'php' option of extra single quotes.
 *
 * @see drush_find_drush()
 */
function hosting_queued_drush_find_drush() {
  $php = drush_get_option('php');
  if (isset($php)) {
    $php = trim($php, "'");
    $drush = array($php, realpath($_SERVER['argv'][0]), "--php='$php'");
  }
  else {
    $drush = array(realpath($_SERVER['argv']['0']));
  }
  return $drush;
}
