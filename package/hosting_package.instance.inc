<?php
/**
 * @file
 * API for mapping packages to various Hosting node types
 */

/**
 * Generate instances to reference nodes to releases.
 *
 * This function uses extensible parameters, so you
 * can pass multiple groups of packages to reference
 * to the node.
 *
 * This mimics Drupal's module and theme override
 * functionality, in that only the top most item
 * will be referenced to the node.
 *
 * @param $rid
 *   The id of the entity on which we are operating.
 * @param $type
 *   The type of the entity on which we are operating (platform, site or
 *   profile).
 */
function hosting_package_instance_sync($rid, $type) {
  \Drupal::database()->update('hosting_package_instance')
    ->fields(array(
      'status' => -1, // @todo remove magic number?
    ))
    ->condition('rid', $rid)
    ->execute();

  $map = _hosting_package_plural_map();
  // phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
  $args = func_get_args();

  $rid = array_shift($args);
  // We need to pass in $type, since there's a possibility of collision b/w a
  // profile's iid, and the id of a site or platform.
  $type = array_shift($args);

  // Figure out the platform id.
  if ($type == 'platform') {
    $platform = $rid;
  }
  elseif ($type == 'site') {
    $site = \Drupal::entityTypeManager()->getStorage('hosting_site')->load($rid);
    if ($site instanceof \Drupal\Core\Entity\ContentEntityInterface) {
      $platform = $site->get('platform')->target_id;
    }
    else {
      $platform = is_object($site) && isset($site->platform) ? $site->platform : NULL;
    }
  }
  elseif ($type == 'profile') {
    $sql = 'SELECT rid
            FROM {hosting_package_instance}
            WHERE iid = :rid';
    $platform = \Drupal::database()->query($sql, array(':rid' => $rid))->fetchField();
  }
  else {
    \Drupal::logger('hosting_package')->warning('Unrecognized entity type (' . $type . ') passed to hosting_package_instance_sync().');
  }

  foreach ($map as $plural => $key) {
    $merged = array();
    foreach ($args as $index => $arg) {
      if (array_key_exists($plural, $args[$index])) {
        $merged = array_merge($merged, $args[$index][$plural]);
      }
    }

    foreach ($merged as $name => $package) {
      $instance = hosting_package_instance_load(array(
        'i.rid' => $rid,
        'i.package_id' => $package['package_id'],
      ));
      if (!$instance) {
        $instance = new stdClass();
        $instance->rid = $rid;
        $instance->package_id = $package['package_id'];
      }
      $instance->languages = isset($package['info']['languages']) ? $package['info']['languages'] : array();
      $instance->filename = isset($package['filename']) ? $package['filename'] : '';
      $instance->version = isset($package['version']) ? $package['version'] : 'Unknown';
      $instance->schema_version = isset($package['schema_version']) ? $package['schema_version'] : 0;
      $instance->version_code = hosting_package_instance_version_code($instance->version);
      $instance->status = array_key_exists('status', $package) ? $package['status'] : 0;
      $instance->platform = isset($package['platform']) ? $package['platform'] : $platform;

      hosting_package_instance_save($instance);
    }
  }
  \Drupal::database()->delete('hosting_package_instance')
    ->condition('rid', $rid)
    ->condition('status', -1) // @todo remove magic number?
    ->execute();
}

function _hosting_package_instance_languages($instance) {
  if (isset($instance->languages) && count($instance->languages)) {
    \Drupal::database()->delete('hosting_package_languages')
      ->condition('iid', $instance->iid)
      ->execute();
    foreach ($instance->languages as $language) {
      $id = \Drupal::database()->insert('hosting_package_languages')
        ->fields(array(
          'iid' => $instance->iid,
          'language' => $language,
        ))
        ->execute();
    }
  }

  // Clear the static cache for languages of package instances.
  _hosting_package_instances_load_languages(NULL, TRUE);
}

/**
 * @todo Please document this function.
 * @see http://drupal.org/node/1354
 */
function hosting_package_instance_update(&$instance) {
  $id = \Drupal::database()->update('hosting_package_instance')
    ->fields(array(
      'rid' => $instance->rid,
      'package_id' => $instance->package_id,
      'filename' => $instance->filename,
      'schema_version' => $instance->schema_version,
      'version' => $instance->version,
      'version_code' => $instance->version_code,
      'status' => $instance->status,
      'platform' => $instance->platform
    ))
    ->condition('iid', $instance->iid)
    ->execute();

  _hosting_package_instance_languages($instance);
}

/**
 * @todo Please document this function.
 * @see http://drupal.org/node/1354
 */
function hosting_package_instance_create(&$instance) {
  $id = \Drupal::database()->insert('hosting_package_instance')
    ->fields(array(
      'rid' => $instance->rid,
      'package_id' => $instance->package_id,
      'filename' => $instance->filename,
      'schema_version' => $instance->schema_version,
      'version' => $instance->version,
      'version_code' => $instance->version_code,
      'status' => $instance->status,
      'platform' => $instance->platform
    ))
    ->execute();
  $instance->iid = $id;
  _hosting_package_instance_languages($instance);
}

/**
 * Get the first package instance object that maches given parameters.
 *
 * @see hosting_package_instances_load()
 */
function hosting_package_instance_load($param) {
  return _hosting_package_instances_load($param);
}

/**
 * Get an array of all package instance objects that match given parameters.
 *
 * @param mixed $param
 *   If $param is an integer it will be matched against an instance iid. If
 *   $param is an array then the key/value pairs will be used as a WHERE clause
 *   in the form "WHERE key0 = value0 AND ... AND keyN = valueN". Both the keys
 *   and values of the $param array must be strings.
 *
 * @return mixed
 *   Returns an array of instance objects keyed by the instance iid. If there
 *   are no matches an empty array will be returned. If there was an error FALSE
 *   will be returned.
 */
function hosting_package_instances_load($param) {
  return _hosting_package_instances_load($param, TRUE);
}

/**
 * Internal function for hosting_package_instance(s)_load.
 */
function _hosting_package_instances_load($param, $multiple = FALSE) {
  $instances = array();

  $related_type = NULL;
  $related_status = NULL;
  if (is_array($param)) {
    if (array_key_exists('r.type', $param)) {
      $related_type = $param['r.type'];
      unset($param['r.type']);
    }
    if (array_key_exists('r.status', $param)) {
      $related_status = $param['r.status'];
      unset($param['r.status']);
    }
    if (array_key_exists('n.status', $param)) {
      $param['i.status'] = $param['n.status'];
      unset($param['n.status']);
    }
  }

  $query = \Drupal::database()->select('hosting_package_instance', 'i')
    ->addTag('hosting_package_instances_load')
    ->fields('i', array('iid', 'version', 'version_code', 'schema_version', 'status', 'rid', 'package_id'));
  $package_alias = $query->leftJoin('hosting_package', 'p', 'i.package_id = %alias.id');
  $query->fields($package_alias, array('package_type', 'id', 'description', 'short_name', 'old_short_name'));
  $query->addField($package_alias, 'label', 'title');
  $site_alias = $query->leftJoin('hosting_site', 's', 'i.rid = %alias.id');
  $platform_alias = $query->leftJoin('hosting_platform', 'h', 'i.rid = %alias.id');
  $query->addExpression("CASE WHEN {$site_alias}.id IS NOT NULL THEN 'site' WHEN {$platform_alias}.id IS NOT NULL THEN 'platform' ELSE '' END", 'type');

  if ($related_type === 'platform') {
    $query->condition($platform_alias . '.id', NULL, 'IS NOT NULL');
  }
  elseif ($related_type === 'site') {
    $query->condition($site_alias . '.id', NULL, 'IS NOT NULL');
  }

  if (isset($related_status)) {
    if ($related_type === 'platform') {
      $query->condition($platform_alias . '.status', $related_status);
    }
    elseif ($related_type === 'site') {
      $query->condition($site_alias . '.status', $related_status);
    }
    else {
      $status_group = $query->orConditionGroup()
        ->condition($site_alias . '.status', $related_status)
        ->condition($platform_alias . '.status', $related_status);
      $query->condition($status_group);
    }
  }

  if (is_numeric($param)) {
    $query->condition('i.iid', $param);
  }
  elseif (is_array($param)) {
    // Turn the conditions into a query.
    foreach ($param as $key => $value) {
      $query->condition($key, $value);
    }
  }
  else {
    return FALSE;
  }

  $result = $query->execute();

  foreach ($result as $instance) {
    $instance->languages = _hosting_package_instances_load_languages($instance->iid);
    if (!$multiple) {
      return $instance;
    }
    $instances[$instance->iid] = $instance;
  }
  return $instances;
}

/**
 * Get the languages associated with an instance of a package.
 */
function _hosting_package_instances_load_languages($instance_id = NULL, $reset = FALSE) {
  static $instances = array();
  if (!empty($reset)) {
    $instances = array();
  }

  // Build the array of languages if requested and we need to.
  if (!is_null($instance_id) && !isset($instances[$instance_id])) {
    $languages = array('en' => 'en');

    // load language options: if argument is null, load all language options
    $lang_result = \Drupal::database()->query("SELECT DISTINCT(language) FROM {hosting_package_languages} WHERE iid = :iid", array(':iid' => $instance_id));
    while ($language = $lang_result->fetch()) {
      $languages[$language->language] = _hosting_language_name($language->language);
    }

    $instances[$instance_id] = $languages;
  }

  // Return the array of languages if requested.
  return !is_null($instance_id) ? $instances[$instance_id] : NULL;

}


/**
 * @todo Please document this function.
 * @see http://drupal.org/node/1354
 */
function hosting_package_instance_save(&$instance) {
  if (!isset($instance->iid)) {
    hosting_package_instance_create($instance);
  }
  else {
    hosting_package_instance_update($instance);
  }
}

/**
 * @todo Please document this function.
 * @see http://drupal.org/node/1354
 */
function hosting_package_instance_delete($instance) {
  \Drupal::database()->delete('hosting_package_languages')
    ->condition('iid', $instance->iid)
    ->execute();
  \Drupal::database()->delete('hosting_package_instance')
    ->condition('iid', $instance->iid)
    ->execute();
}

/**
 * Turn package version string into a number
 *
 * This function returns a float value for the number that represents the
 * version string.
 */
function hosting_package_instance_version_code($version) {

  $defaults = array(
    'platform_major' => 0,
    'platform_minor' => 0,
    'package_major' => 0,
    'package_minor' => 0,
    'package_version_type' => 'release',
    'package_patch_level' => 0,
  );

  $release_types = array(
    '' => 0,
    'dev' => 0,
    'unstable' => 1,
    'alpha' => 2,
    'beta' => 3,
    'rc' => 4,
    'release' => 5,
  );

  $regex = array(
    'core' => '/^(?P<platform_major>\d?)\.(?P<platform_minor>[x\d]*)?(-(?P<package_version_type>dev|unstable|alpha|beta|rc)?(?P<package_patch_level>\d)*)?$/',
    'contrib' => "/(?P<platform_major>\d?)\.(?P<platform_minor>[x\d]*)?(-(?P<package_major>\d*)?\.(?P<package_minor>[x\d]*)?(-(?P<package_version_type>alpha|unstable|beta|rc|dev)?(?P<package_patch_level>[\d]*)?)?)?/",
  );

  $matches = array();
  if (preg_match($regex['core'], $version, $matches)) {
    $matches = array_merge($defaults, $matches);
  }
  elseif (preg_match($regex['contrib'], $version, $matches)) {
    $matches = array_merge($defaults, $matches);
  }
  else {
    return 0;
  }
  // we use the float type because php generally has a maximum integer value to small to handle this value
  $result =  (float) sprintf("%d%02d%02d%02d%02d%02d",
    $matches['platform_major'], $matches['platform_minor'], $matches['package_major'],
    $matches['package_minor'], $release_types[$matches['package_version_type']],
    $matches['package_patch_level']);

  return $result;
}

/**
 * @todo Please document this function.
 * @see http://drupal.org/node/1354
 */
function hosting_package_comparison($current, $target) {

  $current_table = _hosting_package_temporary_table($current);
  $target_table = _hosting_package_temporary_table($target);

  $status = array();
  $result = \Drupal::database()->query("SELECT count(c.id) AS error
                      FROM $current_table c
                      LEFT JOIN $target_table t
                      ON c.id=t.id
                      WHERE (t.schema_version > 0) && (c.schema_version > t.schema_version)
                      AND c.status = :cstatus",
                      array(
                        ':cstatus' => 1,
                        )
                      );

  foreach ($result as $obj) {
    $status['error'] = $obj->error;
  }

  $result = \Drupal::database()->query("SELECT COUNT(c.id) AS missing
                      FROM $current_table c
                      LEFT JOIN $target_table t
                      ON c.id=t.id
                      WHERE t.id IS NULL
                      AND c.status = :c_status",
                      array(
                        ':c_status' => 1,
                        )
                      );
  foreach ($result as $obj) {
    $status['missing'] = $obj->missing;
  }

  $result = \Drupal::database()->query("SELECT COUNT(c.id) as upgrade
                      FROM $current_table c
                      LEFT JOIN $target_table t
                      ON c.id=t.id
                      WHERE (c.version_code < t.version_code) OR (c.schema_version < t.schema_version)
                      AND c.status = :c_status",
                      array(
                        ':c_status' => 1,
                        )
                      );

  foreach ($result as $obj) {
    $status['upgrade'] = $obj->upgrade;
  }

  $result = \Drupal::database()->query("SELECT count(c.id) AS downgrade
                      FROM $current_table c
                      LEFT JOIN $target_table t
                      ON c.id=t.id
                      WHERE (c.version_code > t.version_code)");

  foreach ($result as $obj) {
    $status['downgrade'] = $obj->downgrade;
  }

  return $status;
}


function _hosting_package_temporary_table($ref) {
  static $names = array();

  if (!isset($names[$ref])) {
    // Generate a unique table name for this $ref.
    $table = 'hosting_pkg_tmp_' . uniqid('', FALSE);
    \Drupal::database()->query("CREATE TEMPORARY TABLE {{$table}} AS SELECT p.id AS id, p.short_name, p.old_short_name, i.version_code, i.schema_version, i.version, i.status
              FROM {hosting_package} p
              LEFT JOIN {hosting_package_instance} i
              ON p.id = i.package_id
              WHERE i.rid = :rid and i.platform != :platform",
              array(':rid' => $ref, ':platform' => HOSTING_PACKAGE_INSTANCE_EXCLUDE));
    $names[$ref] = $table;
  }

  return $names[$ref];
}

/**
 * Helper to determine if a particular site has a module installed.
 */
function _hosting_package_site_has_module($node, $module) {
  $package = hosting_package_instance_load(array('rid' => hosting_entity_id($node), 'p.short_name' => $module));
  if (!is_object($package)) {
    return FALSE;
  }
  return $package->status;
}
