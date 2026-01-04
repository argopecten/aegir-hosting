<?php
/**
 * @file
 * SSL helper routines for hosting site entities.
 */

/**
 * This SSL key is a custom, Aegir generated one.
 */
define('HOSTING_SSL_CUSTOM_KEY', 'null');

/**
 * Form API code to extend the site form with SSL fields.
 */
function hosting_ssl_site_form(&$form, &$form_state, $form_id) {
  $node = $form['#entity'] ?? $form['#node'] ?? NULL;
  if (!$node) {
    return;
  }

  $ssl_available = FALSE;
  // Only allow the user to modify these values when the platform is SSL enabled.
  $site_id = hosting_entity_id($node);
  if ($site_id) {
    $platform_id = $node instanceof \Drupal\Core\Entity\ContentEntityInterface ? $node->get('platform')->target_id : $node->platform;
    $platform = $platform_id ? \Drupal::entityTypeManager()->getStorage('hosting_platform')->load($platform_id) : NULL;
    $server_id = $platform instanceof \Drupal\Core\Entity\ContentEntityInterface ? $platform->get('web_server')->target_id : (isset($platform->web_server) ? $platform->web_server : NULL);
    $server = $server_id ? \Drupal::entityTypeManager()->getStorage('hosting_server')->load($server_id) : NULL;

    $http_service = $server ? hosting_server_service($server, 'http') : NULL;
    if ($http_service && $http_service->ssl_enabled) {
      $ssl_available = TRUE;
    }
  }
  elseif (count(hosting_ssl_get_servers('http')) > 0) {
    $ssl_available = TRUE;
  }


  if (!$ssl_available) {
    return;
  }

  $form['hosting_ssl_wrapper'] = array(
    '#type' => 'details',
    '#title' => t('SSL Settings'),
    '#open' => TRUE,
    '#weight' => 50,
  );

  $form['hosting_ssl_wrapper']['ssl_enabled'] = array(
    '#type' => 'radios',
    '#title' => t('Encryption'),
    '#options' => hosting_ssl_status_options(),
    '#description' => t('Enabling encryption will publish your site on both HTTP and HTTPS ports, allowing you to redirect users to the more secure version for certain pages that require the additional security. Requiring encryption will automatically redirect all unencrypted traffic to your HTTPS site.'),
    '#required' => TRUE,
    '#default_value' => isset($node->ssl_enabled) ? $node->ssl_enabled : HOSTING_SSL_DISABLED,
    '#parents' => array('ssl_enabled'),
    '#hosting_signup_prefix_skip' => TRUE,
    '#access' => \Drupal::currentUser()->hasPermission('create ssl certificate'),
  );

  $client_id = NULL;
  if ($node instanceof \Drupal\Core\Entity\ContentEntityInterface && $node->hasField('client')) {
    $client_id = $node->get('client')->target_id;
  }
  elseif (isset($node->client)) {
    $client_id = $node->client;
  }

  $form['hosting_ssl_wrapper']['ssl_key'] = array(
    '#type' => 'radios',
    '#title' => t('Encryption key'),
    '#description' => t('Choose an existing SSL certificate. If you do not wish to use any of your existing certificates, you may choose to generate a new one.'),
    '#options' => hosting_ssl_get_keys($client_id, TRUE),
    '#required' => TRUE,
    '#default_value' => (isset($node->ssl_key) && $node->ssl_key > 0) ? $node->ssl_key : HOSTING_SSL_CUSTOM_KEY,
    '#parents' => array('ssl_key'),
    '#hosting_signup_prefix_skip' => TRUE,
    '#access' => \Drupal::currentUser()->hasPermission('create ssl certificate'),
    '#states' => array(
      'visible' => array(
        ':input[name="ssl_enabled"]' => array('!value' => 0),
      ),
    ),
  );

  $form['hosting_ssl_wrapper']['ssl_key_new'] = array(
    '#type' => 'textfield',
    '#title' => t('New encryption key'),
    '#description' => t("A name for the certificate, often relating to the domain name. This field should only contain lower case alpha-numeric and '_', '-' or '.' characters. If the SSL certificate is not found in config/ssl.d, Aegir will automatically generate a self-signed certificate for you. You can replace the generated with a properly signed version later. Any required intermediate certificates can be added in a file called config/ssl.d/&lt;name&gt;/openssl_chain.crt"),
    '#default_value' => '',
    '#parents' => array('ssl_key_new'),
    '#hosting_signup_prefix_skip' => TRUE,
    '#access' => \Drupal::currentUser()->hasPermission('create ssl certificate'),
    '#states' => array(
      'visible' => array(
        ':input[name="ssl_enabled"]' => array('!value' => 0),
        ':input[name="ssl_key"]' => array('value' => HOSTING_SSL_CUSTOM_KEY),
      ),
    ),
  );

  $form['#validate'][] = 'hosting_ssl_site_form_validate';
}

/**
 * Form validation handler for SSL site fields.
 */
function hosting_ssl_site_form_validate($form, \Drupal\Core\Form\FormStateInterface $form_state) {
  $entity = $form['#entity'] ?? $form['#node'] ?? NULL;
  if (!$entity instanceof \Drupal\Core\Entity\ContentEntityInterface || $entity->getEntityTypeId() !== 'hosting_site') {
    return;
  }

  $node = hosting_site_build_validation_stub($form, $form_state, $entity);
  hosting_ssl_site_validate($node, $form, $form_state);
}

/**
 * Implemensts hook_hosting_site_options_alter
 */
function hosting_ssl_hosting_site_options_alter(&$return, $node) {
  // Disable the ssl key fields by default.
  if (!count(hosting_ssl_get_servers())) {
    $return['ssl_enabled'] = FALSE;
  }

  $return['ssl_key'] = FALSE;
  $return['ssl_key_new'] = FALSE;

  // Test if ssl has been enabled.
  //if (isset($node->ssl_enabled)) {
  if (isset($node->ssl_enabled) && $node->ssl_enabled) {

    $keys = hosting_ssl_get_keys($node->client, TRUE);
    // Return the list of valid keys, including the special 'new key' option.
    $return['ssl_key'] = array_keys($keys);

    // Properly default this value so things dont fall apart later.
    if (count($return['ssl_key']) == 1) {
      $node->ssl_key = HOSTING_SSL_CUSTOM_KEY;
    }

    // the user has chosen to enter a new key
    if ($node->ssl_key == HOSTING_SSL_CUSTOM_KEY) {
      // default the new key to the site's domain name, after filtering.
      $default = hosting_ssl_filter_key(hosting_entity_label($node));
      $return['ssl_key_new'] = (!empty($default)) ? $default : TRUE;
    }

    // we need to ensure that the return value is properly indexed, otherwise it
    // gets interpreted as an object by jquery.
    $return['profile'] = array_values(array_intersect($return['profile'], hosting_ssl_get_profiles()));

    $return['platform'] = array_values(array_intersect($return['platform'], hosting_ssl_get_platforms()));
  }
}


/**
 * Add SSL info to the site view build.
 */
function hosting_ssl_site_view(array &$build, \Drupal\Core\Entity\EntityInterface $entity) {
  if ($entity->getEntityTypeId() !== 'hosting_site') {
    return;
  }
  $ssl_enabled = isset($entity->ssl_enabled) ? $entity->ssl_enabled : HOSTING_SSL_DISABLED;
  $build['info']['ssl_enabled'] = array(
    '#type' => 'item',
    '#title' => t('Encryption'),
    '#markup' => hosting_ssl_status_options($ssl_enabled),
    '#weight' => 6,
  );

  if (!empty($ssl_enabled)) {
    $ssl_key = $entity->ssl_key ?? NULL;
    $build['info']['ssl_key'] = array(
      '#type' => 'item',
      '#title' => t('Encryption key'),
      '#markup' => hosting_ssl_output_key($ssl_key),
      '#description' => $ssl_key && hosting_ssl_get_key($ssl_key)
        ? t("This site is using SSL certificates located at %path.", array('%path' => sprintf("config/ssl.d/%s/", hosting_ssl_output_key($ssl_key))))
        : t("(key deleted)"),
      '#weight' => 7,
    );
  }
}

/**
 * @todo Please document this function.
 * @see http://drupal.org/node/1354
 */
function hosting_ssl_get_key($cid) {
  static $cache = array();

  if (!isset($cache[$cid])) {
    $cache[$cid] = \Drupal::database()->query("SELECT ssl_key FROM {hosting_ssl_cert} WHERE cid = :cid", array(':cid' => $cid))->fetchField();
  }
  return $cache[$cid];
}

/**
 * Get an Array of all IP's associated with an SSL certificate.
 */
function hosting_ssl_get_ip($cid) {
  $result = \Drupal::database()->query("SELECT h.name AS server_name, ips.ip_address
                        FROM {hosting_ssl_cert_ips} cert
                  INNER JOIN {hosting_ip_addresses} ips
                          ON cert.ip_address = ips.id
                  INNER JOIN {hosting_context} h
                          ON h.entity_id = ips.server_id
                       WHERE cid = :cid",
                      array(':cid' => $cid));
  $ip_addresses = array();
  foreach ($result as $record) {
    $ip_addresses[$record->server_name] = $record->ip_address;
  }
  return $ip_addresses;
}

/**
 * @todo Please document this function.
 * @see http://drupal.org/node/1354
 */
function hosting_ssl_output_key($cid) {
  return \Drupal\Component\Utility\Xss::filter(hosting_ssl_get_key($cid));
}

/**
 * Output filter for SSL enabled field.
 */
function hosting_ssl_status_options($status = NULL) {
  $options = array(
    HOSTING_SSL_DISABLED => t('Disabled'),
    HOSTING_SSL_ENABLED => t('Enabled'),
    HOSTING_SSL_REQUIRED => t('Required'),
  );

  if (!is_null($status)) {
    return $options[$status];
  }
  return $options;

}


/**
 * Filter disallowed characters from a ssl certificate key.
 *
 * Only lowercase alphanumeric- and '.', '_' or '-' characters are allowed for ssl keys.
 */
function hosting_ssl_filter_key($key) {
  return strtolower(preg_replace("/[^\w\.\-]/", "", $key));
}

/**
 * Validate SSL settings for a site.
 */
function hosting_ssl_site_validate($node, &$form, $form_state = NULL) {
  $set_error = function ($name, $message) use ($form_state) {
    if ($form_state instanceof \Drupal\Core\Form\FormStateInterface) {
      $form_state->setErrorByName($name, $message);
    }
    else {
      \Drupal::messenger()->addError($message);
    }
  };
  if (isset($node->ssl_enabled) && $node->ssl_enabled) {
    // TODO: find a way to avoid calling this function multiple times in hook_validate
    $valid_options = hosting_site_available_options($node);

    if ($node->ssl_key == HOSTING_SSL_CUSTOM_KEY) {
      if (!strlen($node->ssl_key_new)) {
        $set_error('ssl_key_new', t("The encryption key field is required to enable us to generate a new SSL certificate for your site."));
      }
      else {
        $key = hosting_ssl_filter_key($node->ssl_key_new);

        if (($node->ssl_key_new != $key) || !strlen($key)) {
          $set_error('ssl_key_new', t("The encryption key field should only contain lower case alpha-numeric and '_', '-' or '.' characters."));
        }

        if (!ctype_alnum($key[0])) {
          $set_error('ssl_key_new', t("The encryption key field must start with an alpha-numeric character."));
        }

        if ($key == HOSTING_SSL_CUSTOM_KEY) {
          $set_error('ssl_key_new', t("This encryption key value is reserved for internal use, please choose another"));
        }
      }
    }
    else {
      if (!in_array($node->ssl_key, $valid_options['ssl_key'])) {
        $set_error('ssl_key', t("You have chosen an invalid SSL key"));
      }
    }
    // TODO, check that the server to host this site has ssl enabled.
    // if (!empty($node->platform)) {
      // $platform = node_load($node->platform);
      // $server = node_load($platform->web_server);
      // $server->services['http']->type == 'apache_ssl' or nginx_ssl???
    // }
  }
}

/**
 * Prepare SSL fields before saving a site.
 */
function hosting_ssl_site_presave($node) {
  if (!$node instanceof \Drupal\Core\Entity\EntityInterface || $node->getEntityTypeId() !== 'hosting_site') {
    return;
  }
  if (!isset($node->ssl_key)) {
    $node->ssl_key = HOSTING_SSL_CUSTOM_KEY;
  }
  if (!isset($node->ssl_enabled)) {
    $node->ssl_enabled = HOSTING_SSL_DISABLED;
  }
  // This creates the SSL key for the site, if required.
  $node->ssl_key = hosting_ssl_save_key($node);
}

/**
 * Persist SSL settings after a site insert.
 */
function hosting_ssl_site_insert($node) {
  if (!$node instanceof \Drupal\Core\Entity\EntityInterface || $node->getEntityTypeId() !== 'hosting_site') {
    return;
  }
  $site_id = hosting_entity_id($node);
  $id = \Drupal::database()->insert('hosting_ssl_site')
    ->fields(array(
      'site_id' => $site_id,
      'ssl_enabled' => $node->ssl_enabled,
      'ssl_key' => $node->ssl_key,
    ))
    ->execute();
}

/**
 * Persist SSL settings after a site update.
 */
function hosting_ssl_site_update($node) {
  if (!$node instanceof \Drupal\Core\Entity\EntityInterface || $node->getEntityTypeId() !== 'hosting_site') {
    return;
  }
  $site_id = hosting_entity_id($node);
  // check if an existing record is there
  $result = \Drupal::database()->query("SELECT ssl_enabled FROM {hosting_ssl_site} WHERE site_id = :site_id", array(':site_id' => $site_id));
  if (!($obj = $result->fetch())) {
    hosting_ssl_site_insert($node);
  }
  else {
    if (hosting_entity_status($node, 'site') == HOSTING_SITE_DELETED || (!$node->ssl_enabled && $node->ssl_key != 0))  {
      hosting_ssl_clean_keys($node);
    }

    \Drupal::database()->update('hosting_ssl_site')
      ->fields(array(
        'ssl_enabled' => $node->ssl_enabled,
        'ssl_key' => $node->ssl_key,
      ))
      ->condition('site_id', $site_id)
      ->execute();
  }
}

/**
 * Load SSL settings for hosting_site entities.
 */
function hosting_ssl_site_load(array $entities) {
  foreach ($entities as $entity) {
    if (!$entity instanceof \Drupal\Core\Entity\EntityInterface || $entity->getEntityTypeId() !== 'hosting_site') {
      continue;
    }
    $site_id = $entity->id();
    $result = \Drupal::database()->query("SELECT ssl_enabled, ssl_key FROM {hosting_ssl_site} WHERE site_id = :site_id",
      array(':site_id' => $site_id)
      )->fetchObject();
    if ($result) {
      $entity->ssl_enabled = $result->ssl_enabled;
      $entity->ssl_key = $result->ssl_key;
    }
  }
}

/**
 * Clean up SSL settings after a site delete.
 */
function hosting_ssl_site_delete($node) {
  if (!$node instanceof \Drupal\Core\Entity\EntityInterface || $node->getEntityTypeId() !== 'hosting_site') {
    return;
  }
  $site_id = hosting_entity_id($node);
  hosting_ssl_clean_keys($node);
  \Drupal::database()->delete('hosting_ssl_site')
    ->condition('site_id', $site_id)
    ->execute();
}

/**
 * Clean up SSL settings for a deleted site revision.
 */
function hosting_ssl_site_delete_revision($node) {
  if (!$node instanceof \Drupal\Core\Entity\EntityInterface || $node->getEntityTypeId() !== 'hosting_site') {
    return;
  }
  $site_id = hosting_entity_id($node);
  \Drupal::database()->delete('hosting_ssl_site')
    ->condition('site_id', $site_id)
    ->execute();
}


/**
 * Store the SSL Cert key in the database.
 */
function hosting_ssl_save_key($node) {
  if (empty($node->ssl_enabled)) {
    return 0;
  }

  $client = hosting_get_client($node->client);

  if ($node->ssl_key == HOSTING_SSL_CUSTOM_KEY && !empty($node->ssl_key_new)) {
    $ssl_key = $node->ssl_key_new;
    $result = \Drupal::database()->query("SELECT * FROM {hosting_ssl_cert} WHERE ssl_key = :ssl_key", array(':ssl_key' => $ssl_key));
    if ($obj = $result->fetch()) {
      // update
      if ($node->client != NULL) {
        $obj->client = hosting_entity_id($client);
      }
      $fields = get_object_vars($obj);
      $cid = $fields['cid'] ?? NULL;
      unset($fields['cid']);
      if ($cid !== NULL) {
        \Drupal::database()->update('hosting_ssl_cert')
          ->fields($fields)
          ->condition('cid', $cid)
          ->execute();
        $node->ssl_key = $cid;
      }
    }
    else {
      // insert
      $obj = new stdClass();
      $obj->ssl_key = $ssl_key;
      $obj->client = hosting_entity_id($client);
      $obj->status = 0;
      $obj->cid = \Drupal::database()->insert('hosting_ssl_cert')
        ->fields([
          'ssl_key' => $obj->ssl_key,
          'client' => $obj->client,
          'status' => $obj->status,
        ])
        ->execute();
      if (!hosting_ip_allocate($obj, $node)) {
        \Drupal::messenger()->addError(t("Unable to allocate IP address for certificate, assuming SNI (Server Name Indication) will work (incompatible with Safari and IE on Windows XP, Android 2.2, etc)."));
      }
      $node->ssl_key = $obj->cid;
    }
  }

  return $node->ssl_key;
}

/**
 * Retrieve an associated array of possible keys.
 *
 * @param $client
 *   The client to filter the keys by.
 * @param $ask_custom
 *   Include the special 'generate new key' value used by the site form.
 *
 * @return array
 */
function hosting_ssl_get_keys($client = NULL, $ask_custom = FALSE) {
  $keys = array();

  if ($ask_custom == TRUE) {
    $keys[HOSTING_SSL_CUSTOM_KEY] = t("Generate a new encryption key.");
  }

  $args = array();
  $query = "SELECT cid, ssl_key FROM {hosting_ssl_cert}";

  if (!is_null($client)) {
    $client = hosting_get_client($client);
    if ($client) {
      $query .= " WHERE client = :client";
      $args[':client'] = hosting_entity_id($client);
    }
  }
  $result = \Drupal::database()->query($query, $args);

  while ($obj = $result->fetchObject()) {
    if (strlen($obj->ssl_key)) {
      $keys[$obj->cid] = $obj->ssl_key;
    }
  }

  return $keys;
}

/**
 * Remove unused SSL keys from the system (but not from the backend).
 *
 * This is designed to be ran on a site's delete task or the site node's deletion.
 */
function hosting_ssl_clean_keys($node) {
  // Check if there are still sites using this site's certificate.
  if (!\Drupal::database()->query("SELECT * FROM {hosting_ssl_site} siteA
                   INNER JOIN {hosting_ssl_site} siteB ON siteA.ssl_key = siteB.ssl_key
                   INNER JOIN {hosting_site} s ON s.id = siteA.site_id
                     WHERE siteA.site_id <> siteB.site_id
                       AND (
                         siteA.ssl_enabled = :ssl_enabled
                         OR
                         siteA.ssl_enabled = :ssl_required
                       )
                       AND siteB.site_id = :siteB_id;",
                 array(
                   ':ssl_enabled' => HOSTING_SSL_ENABLED,
                   ':ssl_required' => HOSTING_SSL_REQUIRED,
                   ':siteB_id' => hosting_entity_id($node),
                 ))->fetchField()) {
    // We need to fetch the ssl_key field from the DB because the object comes
    // from the node edit, so it's gone.
    $site_id = hosting_entity_id($node);
    $ssl_key = \Drupal::database()->query('SELECT ssl_key FROM {hosting_ssl_site} WHERE site_id = :site_id',
      array(
        ':site_id' => $site_id,
      )
    )->fetchField();
    \Drupal::messenger()->addMessage(t("cleaning up unused certificate %cert associated with site %site", array('%cert' => $ssl_key, '%site' => $site_id)));
    \Drupal::database()->delete('hosting_ssl_cert')
      ->condition('cid', $ssl_key)
      ->execute();
    \Drupal::database()->delete('hosting_ssl_cert_ips')
      ->condition('cid', $ssl_key)
      ->execute();
  }
}
