<?php
/**
 * @file
 * Include for functionality related to Hosting module features.
 */

/**
 * This hosting feature is disabled.
 */
define('HOSTING_FEATURE_DISABLED', 0);

/**
 * This hosting feature is enabled.
 */
define('HOSTING_FEATURE_ENABLED', 1);

/**
 * This hosting feature is required.
 */
define('HOSTING_FEATURE_REQUIRED', 2);


/**
 * Determine whether a specific feature of the hosting system is turned on.
 *
 * @param string $feature
 *   The feature to check the status of, e.g. "client" or "platform".
 *
 * @return bool
 *   TRUE if the feature is enabled, FALSE if it is disabled.
 */
function hosting_feature($feature) {
  static $features = array();

  if (!count($features)) {
    $features = hosting_get_features();
  }
  if (isset($features[$feature]['status']) && ($features[$feature]['status'] == HOSTING_FEATURE_REQUIRED)) {
    $return = \Drupal::moduleHandler()->moduleExists($features[$feature]['module']) ? HOSTING_FEATURE_REQUIRED : HOSTING_FEATURE_DISABLED;
  }
  elseif (isset($features[$feature]['module'])) {
    $return = (\Drupal::moduleHandler()->moduleExists($features[$feature]['module'])) ? HOSTING_FEATURE_ENABLED : HOSTING_FEATURE_DISABLED;
  }
  else {
    $config = \Drupal::config('hosting.features');
    $stored = $config->get('features.' . $feature);
    $return = $stored !== NULL ? (int) $stored : (int) !empty($features[$feature]['status']);
  }
  return $return;
}

/**
 * Get a listing of all known Hosting features.
 *
 * @param bool $refresh
 *   (optional) Pass in TRUE to force the list of features to be rebuilt and not
 *   returned from the cache.
 *
 * @return array
 *   An array of Hosting features.
 *
 * @see hook_hosting_feature()
 */
function hosting_get_features($refresh = FALSE) {
  $cache = \Drupal::cache()->get('hosting_features');

  if (empty($cache->data) || $refresh) {
    $module_list = \Drupal::service('extension.list.module');
    $module_extensions = $module_list->getList();

    $features = array();
    // Include hosting.feature.*.yml files from all discovered modules.
    foreach ($module_extensions as $name => $extension) {
      $path = $extension->getPath();
      if (!$path) {
        continue;
      }
      $feature_files = glob($path . '/hosting.feature.*.yml');
      if (empty($feature_files)) {
        continue;
      }
      foreach ($feature_files as $file) {
        $definition = \Symfony\Component\Yaml\Yaml::parseFile($file);
        if (!is_array($definition) || empty($definition)) {
          continue;
        }
        if (isset($definition['features']) && is_array($definition['features'])) {
          $definition = $definition['features'];
        }
        foreach ($definition as $feature => $info) {
          if (!is_array($info)) {
            continue;
          }
          if (isset($info['title']) && is_string($info['title'])) {
            $info['title'] = t($info['title']);
          }
          if (isset($info['description']) && is_string($info['description'])) {
            $info['description'] = t($info['description']);
          }
          if (isset($info['status'])) {
            $info['status'] = hosting_feature_normalize_status($info['status']);
          }
          if (empty($info['module'])) {
            $info['module'] = $name;
          }
          $features[$feature] = $info;
        }
      }
    }

    // Add module metadata, dependencies, and enabled status.
    $module_handler = \Drupal::moduleHandler();
    $modules = array();
    foreach ($features as $feature => $info) {
      if (!isset($features[$feature]['dependencies'])) {
        $features[$feature]['dependencies'] = array(
          'modules' => array(),
          'features' => array(),
          'reverse' => array(),
        );
      }
      if (empty($info['module'])) {
        continue;
      }
      $module = $info['module'];
      $module_info = array();
      if (isset($module_extensions[$module])) {
        $extension = $module_extensions[$module];
        if (method_exists($extension, 'getInfo')) {
          $module_info = $extension->getInfo();
        }
        elseif (property_exists($extension, 'info')) {
          $module_info = $extension->info;
        }
      }

      if (!empty($module_info['required'])) {
        $features[$feature]['status'] = HOSTING_FEATURE_REQUIRED;
      }
      elseif (!isset($features[$feature]['status'])) {
        $features[$feature]['status'] = HOSTING_FEATURE_DISABLED;
      }

      $features[$feature]['required'] = ($features[$feature]['status'] == HOSTING_FEATURE_REQUIRED);
      $features[$feature]['optional'] = ($features[$feature]['status'] != HOSTING_FEATURE_REQUIRED);
      $features[$feature]['enabled'] = $module_handler->moduleExists($module);
      $features[$feature]['package'] = $module_info['package'] ?? '';
      $features[$feature]['module_info'] = array(
        'name' => $module_info['name'] ?? $module,
        'description' => $module_info['description'] ?? '',
        'package' => $module_info['package'] ?? '',
        'core_version_requirement' => $module_info['core_version_requirement'] ?? '',
      );
      if (empty($features[$feature]['title']) && !empty($module_info['name'])) {
        $features[$feature]['title'] = $module_info['name'];
      }
      if (empty($features[$feature]['description']) && !empty($module_info['description'])) {
        $features[$feature]['description'] = $module_info['description'];
      }

      $raw_dependencies = $module_info['dependencies'] ?? array();
      $features[$feature]['dependencies']['modules'] = hosting_feature_extract_module_dependencies($raw_dependencies);
      // Generate a list of features keyed by module name.
      $modules[$module] = $feature;
    }

    // Add Hosting feature dependencies, keyed by module.
    foreach ($features as $feature => $info) {
      $module_dependencies = $info['dependencies']['modules'] ?? array();
      foreach ($module_dependencies as $dependency) {
        if (array_key_exists($dependency, $modules) && array_key_exists($modules[$dependency], $features)) {
          $features[$feature]['dependencies']['features'][$dependency] = $modules[$dependency];
          $features[$modules[$dependency]]['dependencies']['reverse'][$info['module']] = $feature;
        }
      }
    }

    \Drupal::cache()->set('hosting_features', $features);
    return $features;
  }
  else {
    return $cache->data;
  }
}

/**
 * Extract module dependency names from .info.yml dependency strings.
 *
 * @param array $raw_dependencies
 *   Raw dependency strings from a module's info.
 *
 * @return array
 *   Normalized module dependency names.
 */
function hosting_feature_extract_module_dependencies($raw_dependencies) {
  $dependencies = array();
  foreach ($raw_dependencies as $dependency) {
    $dependency = trim($dependency);
    $dependency = preg_replace('/\s*\(.*\)$/', '', $dependency);
    $parts = explode(':', $dependency, 2);
    $dependency = end($parts);
    if ($dependency !== '') {
      $dependencies[] = $dependency;
    }
  }
  return $dependencies;
}

/**
 * Normalize feature status values.
 *
 * @param mixed $status
 *   Status value from feature definitions.
 *
 * @return int
 *   Normalized feature status constant.
 */
function hosting_feature_normalize_status($status) {
  if (is_int($status)) {
    return $status;
  }
  $status = strtolower((string) $status);
  switch ($status) {
    case 'required':
      return HOSTING_FEATURE_REQUIRED;
    case 'enabled':
      return HOSTING_FEATURE_ENABLED;
    case 'disabled':
      return HOSTING_FEATURE_DISABLED;
  }
  if (is_numeric($status)) {
    return (int) $status;
  }
  return HOSTING_FEATURE_DISABLED;
}

/**
 * Determine missing module dependencies for a feature module.
 *
 * @param string $module
 *   The module machine name.
 * @param array|null $module_extensions
 *   Optional extension list keyed by module name.
 *
 * @return array
 *   A list of missing module machine names.
 */
function hosting_feature_missing_dependencies($module, $module_extensions = NULL) {
  $module_list = \Drupal::service('extension.list.module');
  if ($module_extensions === NULL) {
    $module_extensions = $module_list->getList();
  }
  if (!isset($module_extensions[$module])) {
    return array($module);
  }
  $extension = $module_extensions[$module];
  if (method_exists($extension, 'getInfo')) {
    $module_info = $extension->getInfo();
  }
  elseif (property_exists($extension, 'info')) {
    $module_info = $extension->info;
  }
  else {
    $module_info = array();
  }

  $raw_dependencies = $module_info['dependencies'] ?? array();
  $dependencies = hosting_feature_extract_module_dependencies($raw_dependencies);
  $missing = array();
  foreach ($dependencies as $dependency) {
    if (!isset($module_extensions[$dependency])) {
      $missing[] = $dependency;
    }
  }
  return $missing;
}

/**
 * Determine which entity types are provided by Hosting features.
 *
 * @param bool $refresh
 *   (optional) Pass in TRUE to force the list of node types to be rebuilt and
 *   not returned from the cache.
 *
 * @return true
 *   An array of entity types keyed by the Hosting feature that provides them.
 */
function hosting_feature_node_types($refresh = FALSE) {
  static $types;

  if (!is_array($types) || $refresh) {
    $features = hosting_get_features($refresh);
    foreach ($features as $feature => $info) {
      if (!empty($info['entity_type'])) {
        $types[$feature] = $info['entity_type'];
      }
    }
  }
  return $types;
}

/**
 * Theme function to display Hosting feature dependencies.
 */
function theme_hosting_feature_dependencies($vars) {
  $return = "<div class=\"admin-required\">{$vars['prefix']}:";
  $last = end($vars['dependencies']);
  reset($vars['dependencies']);
  foreach ($vars['dependencies'] as $module => $require) {
    $return .= ' ' . $vars['features'][$require]['title'];
    $enabled = $vars['features'][$require]['enabled'] ? 'enabled' : 'disabled';
    $return .= " (<span class=\"admin-$enabled\">";
    $return .= $enabled;
    $return .= '</span>)';
    $return .= $require == $last ? '.</div>' : ',';
  }
  return $return;
}

/**
 * Helper function to list Hosting feature permissions.
 */
function hosting_feature_role_perms_table($form, &$form_table, $role_perms) {
  $form = array();
  $form['role_perms'] = array(
    '#type' => 'fieldset',
    '#title' => 'Roles & permissions',
    '#description' => 'Upon enabling this feature, the following roles will be assigned the listed permissions.',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
  $table = '<table><thead><tr><th>Role</th><th>Permissions</th></tr></thead><tbody>';
  foreach ($role_perms as $role => $perms) {
    $table .= '<tr><td>' . $role . '</td>';
    $table .= '<td>' . implode(', ', $perms) . '</td></tr>';
  }
  $table .= '</tbody></table>';
  $form['role_perms']['table'] = array(
    '#markup' => $table,
  );
  return $form;
}


/**
 * Enable one or more Hosting features.
 *
 * @param bool $rebuild
 *   Rebuild caches afterwards, default TRUE.
 * @param bool $enable
 *   Also enable the Drupal module, default TRUE.
 */
function hosting_features_enable($features, $rebuild = TRUE, $enable = TRUE) {
  if (count($features)) {
    $module_installer = \Drupal::service('module_installer');
    $config = \Drupal::configFactory()->getEditable('hosting.features');

    $all_features = hosting_get_features();
    $titles = array();
    foreach ($features as $feature) {
      $titles[] = $all_features[$feature]['title'];
    }
    \Drupal::messenger()->addMessage(t("Enabling %feature feature!plural.", array(
      '%feature' => implode(", ", $titles),
      '!plural' => count($features) > 1 ? 's' : '',
    )));

    foreach ($features as $module => $feature) {
      $config->set('features.' . $feature, HOSTING_FEATURE_ENABLED);
      if ($enable) {
        $success = $module_installer->install(array($module), TRUE);
        if (!$success) {
          $config->set('features.' . $feature, HOSTING_FEATURE_DISABLED);
          $missing = hosting_feature_missing_dependencies($module);
          $missing_list = $missing ? implode(', ', $missing) : t('Unknown');
          \Drupal::messenger()->addMessage(t("An error occurred enabling the `:feature` feature. Missing dependencies: :missing.", array(':feature' => $feature, ':missing' => $missing_list)), 'error');
        }
      }
      if (isset($all_features[$feature]['enable']) && function_exists($callback = $all_features[$feature]['enable'])) {
        $callback();
      }
      $role_perms = isset($all_features[$feature]['role_permissions']) ? $all_features[$feature]['role_permissions'] : array();

      hosting_add_permissions($module, $role_perms);
    }

    $config->save();
    if ($rebuild) {
      hosting_feature_rebuild_caches($features);
    }
  }

}

/**
 * Disable one or more Hosting features.
 *
 * @param bool $rebuild
 *   Rebuild caches afterwards, default TRUE.
 * @param bool $disable_module
 *   Also disable the Drupal module, default TRUE.
 */
function hosting_features_disable($disable, $rebuild = TRUE, $disable_module = TRUE) {
  if (count($disable)) {
    \Drupal::messenger()->addMessage(t("Disabling %feature feature!plural.", array(
      '%feature' => implode(", ", $disable),
      '!plural' => count($disable) > 1 ? 's' : '',
    )));
    $config = \Drupal::configFactory()->getEditable('hosting.features');
    if ($disable_module) {
      \Drupal::service('module_installer')->uninstall(array_keys($disable), TRUE);
    }
    $features = hosting_get_features();
    foreach ($disable as $module => $feature) {
      if (isset($features[$feature]['disable']) && function_exists($callback = $features[$feature]['disable'])) {
        $callback();
      }
      $config->set('features.' . $feature, HOSTING_FEATURE_DISABLED);
    }
    $config->save();
    if ($rebuild) {
      hosting_feature_rebuild_caches();
    }
  }

}

/**
 * Determine the status and actions to take on Hosting features.
 *
 * Given an array of Hosting features and their desired statuses, determine
 * which are enabled, which should be, and which should be disabled.
 */
function hosting_determine_features_status($values) {
  $features = hosting_get_features();
  // List of features currently enabled.
  $enabled = array();
  // List of features to enable.
  $enable = array();
  // List of features to disable.
  $disable = array();

  foreach ($features as $feature => $info) {
    $value = $values['hosting_feature_' . $feature];
    $current = $info['enabled'];
    if ($current) {
      $enabled[$features[$feature]['module']] = $features[$feature]['title'];
      if (!$value) {
        $disable[$features[$feature]['module']] = $features[$feature]['title'];
      }
    }
    elseif ($value) {
      $enable[$features[$feature]['module']] = $feature;
      // Add dependencies to enable.
      if (isset($info['dependencies']['features']) && count($info['dependencies']['features'])) {
        foreach ($info['dependencies']['features'] as $module_dep => $feature_dep) {
          $enable[$module_dep] = $feature_dep;
        }
      }
    }
  }

  // Remove any features we're about to enable from those to disable.
  $disable = array_diff_assoc($disable, $enable);
  // Remove any features already enabled from those to enable.
  $enable = array_diff_assoc($enable, $enabled);

  return array(
    'enabled' => $enabled,
    'enable' => $enable,
    'disable' => $disable,
  );
}

/**
 * Perform necessary task after enabling or disabling Hosting features.
 */
function hosting_feature_rebuild_caches($features = array()) {
  \Drupal::entityTypeManager()->clearCachedDefinitions();
  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

  // Rebuild menu.
  \Drupal::service('router.builder')->rebuild();
  \Drupal::service('plugin.manager.menu.link')->rebuild();

  // Record enabled features in the Hosting features registry (in
  // /var/aegir/.drush/drush.yml)

  // Allow scripts to skip automatic verify tasks by passing '--no-verify'
  if (hosting_get_cli_option('no-verify', FALSE)) {
    return;
  }
  if ($site_id = hosting_get_hostmaster_site_id()) {
    hosting_add_task($site_id, 'verify');
  }
}

/**
 * Add a Hosting Feature's permissions to the appropriate roles.
 *
 * @param string $module
 *   The name of the Hosting Feature module whose permissions we're adding.
 * @param array $role_perms
 *   An array of arrays keyed by the role name. The values are the permissions
 *   to enable.
 */
function hosting_add_permissions($module, $role_perms) {
  // Make sure permissions exist before adding them, since we might want to add
  // permissions from optional modules.
  $permissions = \Drupal::service('user.permissions')->getPermissions();
  foreach ($role_perms as $role => $perms) {
    foreach ($perms as $i => $permission) {
      if (!isset($permissions[$permission])) {
        unset($role_perms[$role][$i]);
      }
    }
  }

  // The Aegir admin role automatically gets all permissions from any hosting module.
  if (\Drupal::moduleHandler()->moduleExists($module)) {
    $perms = \Drupal::moduleHandler()->invoke($module, 'permission');
    if (is_array($perms)) {
      foreach (array_keys($perms) as $perm) {
        $role_perms['aegir administrator'][] = $perm;
      }
    }
  }

  $role_storage = \Drupal::entityTypeManager()->getStorage('user_role');
  $roles = $role_storage->loadMultiple();
  foreach ($roles as $role_id => $role) {
    $label = $role->label();
    $grant = NULL;
    if (array_key_exists($label, $role_perms)) {
      $grant = $role_perms[$label];
    }
    elseif (array_key_exists($role_id, $role_perms)) {
      $grant = $role_perms[$role_id];
    }
    if (!$grant) {
      continue;
    }
    foreach ($grant as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();
    \Drupal::messenger()->addMessage(t("The '%role' role was assigned the following permission!plural: '%perms'.", array(
      '%role' => $label,
      '%perms' => implode('\', \'', $grant),
      '!plural' => count($grant) > 1 ? 's' : '',
    )));
  }
}
