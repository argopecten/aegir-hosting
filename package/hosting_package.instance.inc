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
 *   The nid of the entity on which we are operating.
 * @param $type
 *   The type of the entity on which we are operating (platform, site or
 *   profile.
 */
function hosting_package_instance_sync($rid, $type) {
  db_update('hosting_package_instance')
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
  // profile's iid, and the nid of a site or platform.
  $type = array_shift($args);

  // Figure out the platform nid
  if ($type == 'platform') {
    $platform = $rid;
  }
  elseif ($type == 'site') {
    $node = node_load($rid);
    $platform = $node->platform;
  }
  elseif ($type == 'profile') {
    $sql = 'SELECT rid
            FROM {hosting_package_instance}
            WHERE iid = :rid';
    $platform = db_query($sql, array(':rid' => $rid))->fetchField();
  }
  else {
    watchdog('hosting_package', 'Unrecognized entity type (' . $type . ') passed to hosting_package_instance_sync().', array(), WATCHDOG_WARNING);
  }

  foreach ($map as $plural => $key) {
    $merged = array();
    foreach ($args as $index => $arg) {
      if (is_array($args[$index]) && array_key_exists($plural, $args[$index])) {
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
  db_delete('hosting_package_instance')
    ->condition('rid', $rid)
    ->condition('status', -1) // @todo remove magic number?
    ->execute();
}

function _hosting_package_instance_languages($instance) {
  if (isset($instance->languages) && count($instance->languages)) {
    db_delete('hosting_package_languages')
      ->condition('iid', $instance->iid)
      ->execute();
    foreach ($instance->languages as $language) {
      $id = db_insert('hosting_package_languages')
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
  $id = db_update('hosting_package_instance')
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
  $id = db_insert('hosting_package_instance')
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

  $query = db_select('hosting_package_instance', 'i')
    ->addTag('hosting_package_instances_load')
    ->fields('i', array('iid', 'version', 'version_code', 'schema_version', 'status', 'rid', 'package_id'));
  $package_alias = $query->leftJoin('hosting_package', 'p', 'i.package_id = %alias.nid');
  $query->fields($package_alias, array('package_type', 'nid', 'description', 'short_name', 'old_short_name'));
  $node_package_alias = $query->leftJoin('node', 'n', $package_alias . '.nid = %alias.nid');
  $query->fields($node_package_alias, array('title'));
  $node_instance_alias = $query->leftJoin('node', 'r', 'i.rid = %alias.nid');
  $query->fields($node_instance_alias, array('type'));
  $platform_alias = $query->leftJoin('hosting_platform', 'h', $node_instance_alias . '.nid = %alias.nid');

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
    $lang_result = db_query("SELECT DISTINCT(language) FROM {hosting_package_languages} WHERE iid = :iid", array(':iid' => $instance_id));
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
  db_delete('hosting_package_languages')
    ->condition('iid', $instance->iid)
    ->execute();
  db_delete('hosting_package_instance')
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
  $result = db_query("SELECT count(c.nid) AS error
                      FROM $current_table c
                      LEFT JOIN $target_table t
                      ON c.nid=t.nid
                      WHERE (t.schema_version > 0) && (c.schema_version > t.schema_version)
                      AND c.status = :cstatus",
                      array(
                        ':cstatus' => 1,
                        )
                      );

  foreach ($result as $obj) {
    $status['error'] = $obj->error;
  }

  $result = db_query("SELECT COUNT(c.nid) AS missing
                      FROM $current_table c
                      LEFT JOIN $target_table t
                      ON c.nid=t.nid
                      WHERE t.nid IS NULL
                      AND c.status = :c_status",
                      array(
                        ':c_status' => 1,
                        )
                      );
  foreach ($result as $obj) {
    $status['missing'] = $obj->missing;
  }

  $result = db_query("SELECT COUNT(c.nid) as upgrade
                      FROM $current_table c
                      LEFT JOIN $target_table t
                      ON c.nid=t.nid
                      WHERE (c.version_code < t.version_code) OR (c.schema_version < t.schema_version)
                      AND c.status = :c_status",
                      array(
                        ':c_status' => 1,
                        )
                      );

  foreach ($result as $obj) {
    $status['upgrade'] = $obj->upgrade;
  }

  $result = db_query("SELECT count(c.nid) AS downgrade
                      FROM $current_table c
                      LEFT JOIN $target_table t
                      ON c.nid=t.nid
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
    $names[$ref] = db_query_temporary("SELECT p.nid, p.short_name, p.old_short_name, i.version_code, i.schema_version, i.version, i.status
              FROM {hosting_package} p
              LEFT JOIN {hosting_package_instance} i
              ON p.nid = i.package_id
              WHERE i.rid = :rid and i.platform != :platform",
              array( ':rid' => $ref, ':platform' => HOSTING_PACKAGE_INSTANCE_EXCLUDE));

  }

  return $names[$ref];
}

/**
 * Helper to determine if a particular site has a module installed.
 */
function _hosting_package_site_has_module($node, $module) {
  $package = hosting_package_instance_load(array('rid' => $node->nid, 'p.short_name' => $module));
  if (!is_object($package)) {
    return FALSE;
  }
  return $package->status;
}
