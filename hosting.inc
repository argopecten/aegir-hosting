<?php

/**
 * @file
 *
 * General purpose Hosting module functions.
 *
 * These can be used by both the frontend Hosting module and drush commands.
 */

/**
 * Check if a hostname provided is an ip address.
 *
 * @param string $hostname
 *   The hostname to check.
 *
 * @return bool
 *   TRUE is the $hostname is a valid IP address, FALSE otherwise.
 */
function _hosting_valid_ip($hostname) {
  return is_string(inet_pton($hostname));
}

/**
 * Check if the FQDN provided is valid.
 *
 * @param string $fqdn
 *   The Fully Qualified Domain Name (FQDN) to validate.
 *
 * @return int
 *   An integer greater than 0 if the $fqdn is valid, or 0 or FALSE if it not
 *   valid.
 */
function _hosting_valid_fqdn($fqdn) {
  // Regex is an implementation of RFC1035, a little relaxed to allow
  // commonly registered hostnames (e.g. domaines starting with digits).
  return preg_match("/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.?)+$/i", $fqdn);
}

/**
 * Check if the FQDN provided is valid.
 *
 * This is a different function because wildcards are not part of the
 * RFC and may not be allowed everywhere. For example, the main site
 * title shouldn't have a wildcard entry.
 *
 * @param string $fqdn
 *   The Fully Qualified Domain Name (FQDN) to validate.
 *
 * @return bool
 *   TRUE if the $fqdn is valid, or FALSE if it not valid.
 */
function _hosting_valid_fqdn_wildcard($fqdn) {
  // Regex is an implementation of RFC1035, but allows "star" for wildcards.
  return preg_match("/^(\*\.)?([a-z0-9]([a-z0-9-]*[a-z0-9])?\.?)+$/i", $fqdn) &&
    !preg_match("/^(\*\.)[^.]*$/", $fqdn); // don't allow wildcards on TLDs
}

/**
 * Format a timestamp as a string in a friendly way.
 *
 * @param int $ts
 *   The timestamp to format as a an interval.
 *
 * @return string
 *   Returns a string representing the given timestamp:
 *   - If the timestamp is the current time: 'Now'.
 *   - If the timestamp is 0 or FALSE: 'Never'
 *   - Otherwise formatted as 'X ago' where 'X' is for example 1 year or 1
 *     minute etc.
 *
 * @see format_interval()
 */
function hosting_format_interval($ts) {
  if ($ts == REQUEST_TIME) {
    return t('Now');
  }
  if ($ts <= 1) {
    // Treats the UNIX EPOCH as never.
    return t('Never');
  }

  return t("@interval ago", array("@interval" => \Drupal::service('date.formatter')->formatInterval(REQUEST_TIME - $ts, 1)));
}

/**
 * Format bytes as a human-readable string.
 */
function hosting_format_size($bytes) {
  if ($bytes === NULL) {
    return '';
  }
  $bytes = (float) $bytes;
  $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
  $unit = 0;
  while ($bytes >= 1024 && $unit < count($units) - 1) {
    $bytes /= 1024;
    $unit++;
  }
  $precision = $unit === 0 ? 0 : 2;
  return number_format($bytes, $precision, '.', '') . ' ' . $units[$unit];
}

/**
 * Make a path canonical.
 *
 * This removes duplicate slashes, trailing slashes and /./ occurences. It does
 * not (yet?) resolve .. instances.
 */
function hosting_path_normalize($path) {
  return rtrim(preg_replace('/(\/\/*\.)?\/\/*/', '/', $path), '/');
}

/**
 * Helper function to get the entity ID of the Aegir front-end site.
 */
function hosting_get_hostmaster_site_id() {
  $state = \Drupal::state();
  $hostmaster_id = $state->get('aegir_hostmaster_site_id');
  if (!$hostmaster_id) {
    $hostmaster_id = hosting_context_entity_id('hostmaster');
    if ($hostmaster_id) {
      $state->set('aegir_hostmaster_site_id', $hostmaster_id);
    }
  }
  return $hostmaster_id;
}

/**
 * Helper function to get the entity ID of the Aegir platform.
 */
function hosting_get_hostmaster_platform_id() {
  $hostmaster_site = \Drupal::entityTypeManager()->getStorage('hosting_site')->load(hosting_get_hostmaster_site_id());
  return $hostmaster_site ? $hostmaster_site->get('platform')->target_id : NULL;
}

/**
 * Ensure view configs are installed for a module.
 */
function hosting_install_view_configs($module) {
  $entity_type_manager = \Drupal::entityTypeManager();
  if (!$entity_type_manager->hasDefinition('view')) {
    return;
  }

  $entity_type_manager->clearCachedDefinitions();
  \Drupal::service('views.views_data')->clear();

  $path = \Drupal::service('extension.path.resolver')->getPath('module', $module);
  if (!$path) {
    return;
  }
  $config_path = DRUPAL_ROOT . '/' . $path . '/config/install';
  if (!is_dir($config_path)) {
    return;
  }

  $storage = new \Drupal\Core\Config\FileStorage($config_path);
  $view_storage = $entity_type_manager->getStorage('view');
  foreach ($storage->listAll('views.view.') as $config_name) {
    $view_id = substr($config_name, strlen('views.view.'));
    if ($view_storage->load($view_id)) {
      continue;
    }
    $data = $storage->read($config_name);
    if (!is_array($data)) {
      continue;
    }
    $view = $view_storage->createFromStorageRecord($data);
    if ($view->isInstallable()) {
      $view->trustData()->save();
    }
  }
}

/**
 * Ensure action configs are installed for a module.
 */
function hosting_install_action_configs($module) {
  $entity_type_manager = \Drupal::entityTypeManager();
  if (!$entity_type_manager->hasDefinition('action')) {
    return;
  }

  $entity_type_manager->clearCachedDefinitions();

  $path = \Drupal::service('extension.path.resolver')->getPath('module', $module);
  if (!$path) {
    return;
  }
  $config_path = DRUPAL_ROOT . '/' . $path . '/config/install';
  if (!is_dir($config_path)) {
    return;
  }

  $storage = new \Drupal\Core\Config\FileStorage($config_path);
  $action_storage = $entity_type_manager->getStorage('action');
  foreach ($storage->listAll('system.action.') as $config_name) {
    $action_id = substr($config_name, strlen('system.action.'));
    if ($action_storage->load($action_id)) {
      continue;
    }
    $data = $storage->read($config_name);
    if (!is_array($data)) {
      continue;
    }
    $action = $action_storage->createFromStorageRecord($data);
    if ($action->isInstallable()) {
      $action->trustData()->save();
    }
  }
}

/**
 * Ensure entity form display configs are installed for a module.
 */
function hosting_install_form_display_configs($module) {
  $entity_type_manager = \Drupal::entityTypeManager();
  if (!$entity_type_manager->hasDefinition('entity_form_display')) {
    return;
  }

  $path = \Drupal::service('extension.path.resolver')->getPath('module', $module);
  if (!$path) {
    return;
  }
  $config_path = DRUPAL_ROOT . '/' . $path . '/config/install';
  if (!is_dir($config_path)) {
    return;
  }

  $storage = new \Drupal\Core\Config\FileStorage($config_path);
  $display_storage = $entity_type_manager->getStorage('entity_form_display');
  foreach ($storage->listAll('core.entity_form_display.') as $config_name) {
    $display_id = substr($config_name, strlen('core.entity_form_display.'));
    if ($display_storage->load($display_id)) {
      continue;
    }
    $data = $storage->read($config_name);
    if (!is_array($data)) {
      continue;
    }
    $display = $display_storage->createFromStorageRecord($data);
    if ($display->isInstallable()) {
      $display->trustData()->save();
    }
  }
}
