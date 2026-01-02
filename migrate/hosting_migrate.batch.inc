<?php
/**
 * @file
 * Implemented batch migration of sites.
 */

/**
 * Batch migration of sites between platforms.
 */
function hosting_migrate_platform($form, $form_state, $node) {
  $form['#attached']['library'][] = 'hosting_migrate/admin';

  $step = isset($form_state['storage']['step']) ? $form_state['storage']['step'] : 1;
  $platform_id = hosting_entity_id($node);

  // Step 1 - choose target platform
  if ($step == 1) {
    $result = \Drupal::database()->query("SELECT count(id) as site_count FROM {hosting_site} WHERE status = :status AND verified > :verified AND platform = :platform", array(':status' => HOSTING_SITE_ENABLED, ':verified' => 0, ':platform' => $platform_id))->fetch();

    if ($result->site_count == 0) {
      $form['no_sites'] = array(
        '#markup' => t('This platform does not have any sites that can be migrated.'),
      );
      return $form;
    }

    // TODO: hosting_get_enabled_platforms should be sufficient here, except
    // that we haven't implemented node-level access control on platforms yet.
    // See: http://drupal.org/node/725952.
    if (function_exists('_hosting_get_allowed_platforms')) {
      $platforms = _hosting_get_allowed_platforms();
    }
    else {
      $platforms = _hosting_get_enabled_platforms();
    }
    if (sizeof($platforms) > 1) {
      unset($platforms[$platform_id]);
      $form['#current_platform'] = $node;
      $form['description'] = array(
        '#type' => 'item',
        '#description' => 'Perform a batch migration of sites from this platform to a target platform.',
      );
      $form['target_platform'] = array(
        '#type' => 'radios',
        '#required' => TRUE,
        '#title' => t('Platform'),
        '#description' => t('Choose where you want to migrate the sites on this platform to'),
        '#options' => $platforms,
      );
      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Submit'),
      );
    }
    else {
      $form['no_platforms'] = array(
        '#markup' => t('There are no alternate platforms to migrate to.'),
      );
    }
  }

  // Step 2 - review sites that pass or fail the requirements to be migrated
  if ($step == 2) {
    $title = array(
      'passed' => t("The following sites will be migrated"),
      'failed' => t("The following sites will not be migrated"),
    );
    $header = array(t('Site'), t('Upgrades'), t('Warnings'), t('Errors'), t('Actions'));
    $options['attributes']['class'] = 'hosting-package-comparison-link';
    foreach (array('passed', 'failed') as $type) {
      if (isset($form_state['storage'][$type]) && sizeof($form_state['storage'][$type])) {

        $rows = array();

        foreach ($form_state['storage'][$type] as $site_id => $url) {
          $form['output'][$type]['title'] = array(
            '#type' => 'markup',
            '#markup' => '<h2>' . $title[$type] . '</h2>',
          );
          $status = $form_state['storage']['status'][$site_id];
          $row = array(array(
              'data' => $url,
              'class' => array('hosting-status'),
            ), $status['upgrade'], $status['missing'], $status['error']);
          if (isset($form_state['storage']['instance'][$site_id])) {
            $link = \Drupal\Core\Link::fromTextAndUrl(t('Compare'), \Drupal\Core\Url::fromUserInput('/hosting/migrate/compare/' . $platform_id . '/' . $form_state['storage']['instance'][$site_id], $options))->toString();
          }
          else {
            $link = t('Profile not found');
          }
          $row[] = $link;
          $rows[] = array(
            'data' => $row,
            'class' => array('hosting-' . _hosting_migrate_site_list_class($status)),
          );
        }
        $form['output'][$type]['table'] = array(
          '#type' => 'markup',
          '#markup' => \Drupal::service('theme.manager')->render('table', array('header' => $header, 'rows' => $rows, 'attributes' => array('class' => array('hosting-table')))),
        );

      }
    }
    if (sizeof($form_state['storage']['passed'])) {
      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Submit'),
      );
    }

  }

  // Step 3 - All migrate tasks have been created, close the modal if needed.
  if ($step == 3) {
    if (!empty($GLOBALS['modalframe_page_template'])) {
      if (\Drupal::moduleHandler()->moduleExists('overlay')) {
        overlay_close_dialog();
      }
    }
    else {
      $redirect = \Drupal\Core\Url::fromUserInput('/hosting/platform/' . $form_state['storage']['target_platform']);
      if ($form_state instanceof \Drupal\Core\Form\FormStateInterface) {
        $form_state->setRedirectUrl($redirect);
      }
      else {
        return new \Symfony\Component\HttpFoundation\RedirectResponse($redirect->toString());
      }
    }
  }
  return $form;
}

function _hosting_migrate_site_list_class($status) {
  if ($status['error']) {
    return 'error';
  }
  if ($status['missing']) {
    return 'warning';
  }
  return 'success';
}

/**
 * Implements hook_submit().
 */
function hosting_migrate_platform_submit($form, &$form_state) {
  $step = isset($form_state['storage']['step']) ? $form_state['storage']['step'] : 1;
  switch ($step) {
    case 1:
      $current_platform_id = hosting_entity_id($form['#current_platform']);
      $form_state['storage']['current_platform'] = $current_platform_id;
      $form_state['storage']['target_platform'] = $form_state['values']['target_platform'];
      $max_per_batch = 5;
      $result = \Drupal::database()->query("SELECT id, label FROM {hosting_site} WHERE platform = :platform AND status = :status and verified > :verified ORDER BY label", array(':platform' => $current_platform_id, ':status' => HOSTING_SITE_ENABLED, ':verified' => 0));
      $operations = array();
      while ($site = $result->fetch()) {
        $operations[] = array(
          'hosting_migrate_platform_batch',
          array($site->id, $form_state['values']['target_platform'], $form_state),
        );
      }
      if (sizeof($operations)) {
        $batch = array(
          'operations' => $operations,
          'finished' => 'hosting_migrate_platform_finished',
          'title' => t('Checking for sites that can be migrated.'),
          'init_message' => t('Retrieving list of sites.'),
          'progress_message' => t('Evaluated @current out of @total sites.'),
          'error_message' => t('Bulk migration has encountered an error.'),
          'file' => \Drupal::service('extension.list.module')->getPath('hosting_migrate') . '/hosting_migrate.batch.inc',
        );
        batch_set($batch);

        if (!empty($GLOBALS['modalframe_page_template'])) {
          $batch = &batch_get();
          $batch['url'] = 'hosting/js/batch';
          $batch['source_page'] = 'hosting/js/' . $_GET['q'];
        }
      }
      break;
    case 2:
      // Create a task node for logging purposes.
      $current = $form_state['storage']['current_platform'];
      $target = $form_state['storage']['target_platform'];

      $task = hosting_add_task($current, 'migrate', array('target_platform' => $target), HOSTING_TASK_SUCCESS);

      $operations = array();
      foreach ($form_state['storage']['passed'] as $site_id => $url) {
        $operations[] = array(
          'hosting_migrate_platform_submit_batch',
          array($site_id, $form_state['storage']['target_platform'], $url, $task->id()),
        );
      }

      if (sizeof($operations)) {
        $batch = array(
          'operations' => $operations,
          'finished' => 'hosting_migrate_platform_submit_finished',
          'title' => t('Submitting sites for migration.'),
          'init_message' => t('Retrieving list of sites.'),
          'progress_message' => t('Submitted @current out of @total sites.'),
          'error_message' => t('Bulk migration has encountered an error.'),
          'file' => \Drupal::service('extension.list.module')->getPath('hosting_migrate') . '/hosting_migrate.batch.inc',
        );
        batch_set($batch);

        if (!empty($GLOBALS['modalframe_page_template'])) {
          $batch = &batch_get();
          $batch['url'] = 'hosting/js/batch';
          $batch['source_page'] = 'hosting/js/' . $_GET['q'];
        }
      }

      break;
  }
  $form_state['rebuild'] = TRUE;
  $form_state['storage']['step'] = $step + 1;
}

/**
 * Batch callback for platform migration submission.
 */
function hosting_migrate_platform_submit_batch($site_id, $target_platform, $url, $platform_task_vid) {
  $site = \Drupal::entityTypeManager()->getStorage('hosting_site')->load($site_id);
  if (!$site) {
    return;
  }
  hosting_add_task($site_id, 'migrate', array(
    'target_platform' => $target_platform,
    'new_uri' => $url,
    'new_db_server' => $site->get('db_server')->target_id,
  ));
  hosting_task_log($platform_task_vid, 'success', t("Migrate task for !url created", array('!url' => $url)));
}

/**
 * Batch finished callback for platform migration submission.
 */
function hosting_migrate_platform_submit_finished() {
  \Drupal::messenger()->addMessage(t('The sites have been added to the task queue to be migrated'));
}

/**
 * Batch comparison of site packages between platforms to determine
 * if the site can be migrated to the target platform or not.
 */
function hosting_migrate_platform_batch($site_id, $target_platform, $context) {
  if (!isset($context['sandbox']['progress'])) {
    $context['sandbox']['progress'] = 0;
  }
  $site = \Drupal::entityTypeManager()->getStorage('hosting_site')->load($site_id);
  if (!$site) {
    return;
  }
  $profile_value = $site->get('profile')->value;
  $profile = is_numeric($profile_value) ? \Drupal::entityTypeManager()->getStorage('hosting_package')->load($profile_value) : NULL;
  $profile_name = $profile ? $profile->get('short_name')->value : $profile_value;
  $batch = &batch_get();
  // Determine whether the install profile is available on the target platform
  $profile_instance = hosting_package_instance_load(
    array(
    'i.rid' => $target_platform,
    'p.short_name' => $profile_name,
  ));
  if ($profile_instance) {
    $status = hosting_package_comparison($site->id(), $profile_instance->iid);
    $batch['form_state']['storage']['status'][$site->id()] = $status;
    $batch['form_state']['storage']['instance'][$site->id()] = $profile_instance->iid;
    // If there were no errors, this site passes and can be migrated
    if (!$status['error'] && $profile_name != 'hostmaster') { // The hostmaster site can not be upgraded via the interface.
      $batch['form_state']['storage']['passed'][$site->id()] = hosting_site_get_domain($site->label());
      return TRUE;
    }
  }
  else {
    $batch['form_state']['storage']['status'][$site->id()] = array(
      'error' => 1,
      'missing' => 0,
      'upgrade' => 0,
    );
  }
  $batch['form_state']['storage']['failed'][$site->id()] = hosting_site_get_domain($site->label());
}
