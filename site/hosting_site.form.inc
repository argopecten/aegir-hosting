<?php
/**
 * @file
 * Site entity form helpers.
 */

/**
 * Fetch a form widget element for a field.
 */
function &hosting_site_form_get_widget_element(array &$form, $field_name, array $value_keys) {
  $null = NULL;
  if (!isset($form[$field_name]['widget'][0])) {
    return $null;
  }
  foreach ($value_keys as $key) {
    if (isset($form[$field_name]['widget'][0][$key])) {
      return $form[$field_name]['widget'][0][$key];
    }
  }
  return $null;
}

/**
 * Read a widget value from form state.
 */
function hosting_site_form_get_field_value(array $form, $form_state, $field_name, array $value_keys) {
  if (isset($form[$field_name]['widget'][0])) {
    foreach ($value_keys as $key) {
      if (isset($form[$field_name]['widget'][0][$key]['#parents'])) {
        $parents = $form[$field_name]['widget'][0][$key]['#parents'];
        $value = NULL;
        if (is_object($form_state) && method_exists($form_state, 'getValue')) {
          $value = $form_state->getValue($parents);
        }
        elseif (isset($form_state['values'])) {
          $value = \Drupal\Component\Utility\NestedArray::getValue($form_state['values'], $parents);
        }
        if ($value !== NULL && $value !== '') {
          return $value;
        }
      }
    }
  }

  if (is_object($form_state) && method_exists($form_state, 'getValue')) {
    $value = $form_state->getValue($field_name);
  }
  else {
    $value = $form_state['values'][$field_name] ?? NULL;
  }

  if (is_array($value)) {
    foreach ($value_keys as $key) {
      if (isset($value[0][$key])) {
        return $value[0][$key];
      }
    }
  }

  return $value;
}

/**
 * Resolve an error name for a field widget.
 */
function hosting_site_form_error_name(array $form, $field_name, array $value_keys) {
  if (isset($form[$field_name]['widget'][0])) {
    foreach ($value_keys as $key) {
      if (isset($form[$field_name]['widget'][0][$key]['#parents'])) {
        return implode('][', $form[$field_name]['widget'][0][$key]['#parents']);
      }
    }
  }
  return $field_name;
}

/**
 * Set widget options and defaults for a field.
 */
function hosting_site_form_set_widget_options(array &$form, $field_name, array $options, $default_value = NULL) {
  $element = &hosting_site_form_get_widget_element($form, $field_name, array('value', 'target_id'));
  if ($element !== NULL) {
    $element['#options'] = $options;
    if ($default_value !== NULL) {
      $element['#default_value'] = $default_value;
    }
    return;
  }

  if (isset($form[$field_name]['#options'])) {
    $form[$field_name]['#options'] = $options;
    if ($default_value !== NULL) {
      $form[$field_name]['#default_value'] = $default_value;
    }
  }
}

/**
 * Disable widget editing for a field.
 */
function hosting_site_form_disable_widget(array &$form, $field_name, array $value_keys) {
  $element = &hosting_site_form_get_widget_element($form, $field_name, $value_keys);
  if ($element !== NULL) {
    $element['#disabled'] = TRUE;
  }
}

/**
 * Build a legacy-style site object from form values.
 */
function hosting_site_build_validation_stub(array $form, $form_state, $entity) {
  $node = new stdClass();

  $label = hosting_site_form_get_field_value($form, $form_state, 'label', array('value'));
  if (($label === NULL || $label === '') && $entity instanceof \Drupal\Core\Entity\ContentEntityInterface) {
    $label = $entity->label();
  }
  $node->label = $label;

  $profile = hosting_site_form_get_field_value($form, $form_state, 'profile', array('value'));
  if ($profile === NULL && $entity instanceof \Drupal\Core\Entity\ContentEntityInterface && $entity->hasField('profile')) {
    $profile = $entity->get('profile')->value;
  }
  $node->profile = $profile;

  $platform = hosting_site_form_get_field_value($form, $form_state, 'platform', array('target_id'));
  if ($platform === NULL && $entity instanceof \Drupal\Core\Entity\ContentEntityInterface && $entity->hasField('platform')) {
    $platform = $entity->get('platform')->target_id;
  }
  $node->platform = $platform;

  $language = hosting_site_form_get_field_value($form, $form_state, 'language', array('value'));
  if ($language === NULL && $entity instanceof \Drupal\Core\Entity\ContentEntityInterface && $entity->hasField('language')) {
    $language = $entity->get('language')->value;
  }
  $node->language = $language;

  $client = hosting_site_form_get_field_value($form, $form_state, 'client', array('target_id'));
  if ($client === NULL && $entity instanceof \Drupal\Core\Entity\ContentEntityInterface && $entity->hasField('client')) {
    $client = $entity->get('client')->target_id;
  }
  $node->client = $client;

  $ssl_enabled = hosting_site_form_get_field_value($form, $form_state, 'ssl_enabled', array('value'));
  if ($ssl_enabled !== NULL) {
    $node->ssl_enabled = $ssl_enabled;
  }
  $ssl_key = hosting_site_form_get_field_value($form, $form_state, 'ssl_key', array('value'));
  if ($ssl_key !== NULL) {
    $node->ssl_key = $ssl_key;
  }
  $ssl_key_new = hosting_site_form_get_field_value($form, $form_state, 'ssl_key_new', array('value'));
  if ($ssl_key_new !== NULL) {
    $node->ssl_key_new = $ssl_key_new;
  }

  $new_client = NULL;
  if (is_object($form_state) && method_exists($form_state, 'getValue')) {
    $new_client = $form_state->getValue('new_client');
  }
  elseif (isset($form_state['values']['new_client'])) {
    $new_client = $form_state['values']['new_client'];
  }
  if ($new_client !== NULL) {
    $node->new_client = $new_client;
  }

  return $node;
}

/**
 * Apply dynamic options and behavior to the site form widgets.
 */
function hosting_site_apply_form_overrides(array &$form, &$form_state, $entity) {
  if (!$entity instanceof \Drupal\Core\Entity\ContentEntityInterface || $entity->getEntityTypeId() !== 'hosting_site') {
    return;
  }

  $profile_value = hosting_site_form_get_field_value($form, $form_state, 'profile', array('value'));
  $platform_value = hosting_site_form_get_field_value($form, $form_state, 'platform', array('target_id'));
  $language_value = hosting_site_form_get_field_value($form, $form_state, 'language', array('value'));
  $db_server_value = hosting_site_form_get_field_value($form, $form_state, 'db_server', array('target_id'));

  if ($profile_value === NULL && $entity->hasField('profile')) {
    $profile_value = $entity->get('profile')->value;
  }
  if ($platform_value === NULL && $entity->hasField('platform')) {
    $platform_value = $entity->get('platform')->target_id;
  }
  if ($language_value === NULL && $entity->hasField('language')) {
    $language_value = $entity->get('language')->value;
  }
  if ($language_value === NULL || $language_value === '') {
    $language_value = 'en';
  }
  if ($db_server_value === NULL && $entity->hasField('db_server')) {
    $db_server_value = $entity->get('db_server')->target_id;
  }

  $platform_query = \Drupal::request()->query->get('platform');
  if ($entity->isNew() && $platform_query && is_numeric($platform_query)) {
    $platform_value = (int) $platform_query;
  }

  $profile_id = $profile_value;
  if ($profile_id && !is_numeric($profile_id)) {
    $package = hosting_get_package($profile_id, 'profile');
    if ($package) {
      $profile_id = $package->id();
    }
  }
  if (!$profile_id) {
    $profile_id = hosting_get_default_profile();
  }

  $node = (object) array(
    'profile' => $profile_id,
    'platform' => $platform_value,
    'language' => $language_value,
  );

  $ssl_enabled = hosting_site_form_get_field_value($form, $form_state, 'ssl_enabled', array('value'));
  if ($ssl_enabled !== NULL) {
    $node->ssl_enabled = $ssl_enabled;
  }
  $ssl_key = hosting_site_form_get_field_value($form, $form_state, 'ssl_key', array('value'));
  if ($ssl_key !== NULL) {
    $node->ssl_key = $ssl_key;
  }
  $ssl_key_new = hosting_site_form_get_field_value($form, $form_state, 'ssl_key_new', array('value'));
  if ($ssl_key_new !== NULL) {
    $node->ssl_key_new = $ssl_key_new;
  }

  $apply_filters = $entity->isNew();
  $valid_options = $apply_filters ? hosting_site_available_options($node, $platform_query) : array();
  if ($apply_filters && !empty($valid_options['profile'])) {
    $allowed_profiles = array_map('strval', $valid_options['profile']);
    if (!$profile_value || !in_array((string) $profile_value, $allowed_profiles, TRUE)) {
      $profile_value = $valid_options['profile'][0];
    }
    $profile_id = $profile_value;
    if ($profile_id && !is_numeric($profile_id)) {
      $package = hosting_get_package($profile_id, 'profile');
      if ($package) {
        $profile_id = $package->id();
      }
    }
    $node->profile = $profile_id;
    $valid_options = hosting_site_available_options($node, $platform_query);
  }

  if ($apply_filters && !empty($valid_options['profile']) && isset($form['profile'])) {
    $allowed_profiles = array_flip(array_map('strval', $valid_options['profile']));
    $profile_options = $form['profile']['widget'][0]['value']['#options'] ?? array();
    if ($profile_options) {
      $profile_options = array_intersect_key($profile_options, $allowed_profiles);
      $profile_options = array_filter($profile_options, function ($value) {
        return $value !== NULL && $value !== '';
      });
    }

    $default_profile = $profile_value;
    if (!$default_profile || !isset($profile_options[(string) $default_profile])) {
      $default_profile = key($profile_options);
    }
    hosting_site_form_set_widget_options($form, 'profile', $profile_options, $default_profile);
    if ($default_profile && (string) $default_profile !== (string) $profile_value) {
      $profile_value = $default_profile;
      $profile_id = $default_profile;
      if ($profile_id && !is_numeric($profile_id)) {
        $package = hosting_get_package($profile_id, 'profile');
        if ($package) {
          $profile_id = $package->id();
        }
      }
      $node->profile = $profile_id;
      $valid_options = hosting_site_available_options($node, $platform_query);
    }
  }

  if ($apply_filters) {
    $platforms = $profile_id ? hosting_get_profile_platforms($profile_id, isset($node->check_profile_migrations) ? $node->check_profile_migrations : FALSE) : array();
    if (!empty($valid_options['platform'])) {
      $platforms = array_intersect_key($platforms, array_flip($valid_options['platform']));
    }

    if ($platform_query && is_numeric($platform_query) && isset($platforms[$platform_query])) {
      $platform_value = (int) $platform_query;
    }
    if (!$platform_value || !isset($platforms[$platform_value])) {
      $platform_value = key($platforms);
    }
    if ($platforms) {
      hosting_site_form_set_widget_options($form, 'platform', $platforms, $platform_value);
    }

    $languages = hosting_get_profile_languages($profile_id, $platform_value);
    if (!empty($valid_options['language'])) {
      $languages = array_intersect_key($languages, array_flip($valid_options['language']));
    }
    if (!$language_value || !isset($languages[$language_value])) {
      $language_value = $languages ? key($languages) : 'en';
    }
    if ($languages) {
      hosting_site_form_set_widget_options($form, 'language', $languages, $language_value);
    }
  }

  $db_servers = hosting_get_servers('db');
  if ($db_servers) {
    $default_db_server = $db_server_value;
    if (!$default_db_server || !isset($db_servers[$default_db_server])) {
      $default_db_server = array_key_exists(HOSTING_DEFAULT_DB_SERVER, $db_servers) ? HOSTING_DEFAULT_DB_SERVER : key($db_servers);
    }
    hosting_site_form_set_widget_options($form, 'db_server', $db_servers, $default_db_server);
  }

  $profile_element = &hosting_site_form_get_widget_element($form, 'profile', array('value'));
  if ($profile_element !== NULL) {
    $profile_element['#attributes']['class'][] = 'hosting-site-form-profile-options';
    $profile_element['#ajax'] = array(
      'callback' => 'hosting_site_platform_callback',
      'wrapper' => 'hosting-site-platform-wrapper',
      'effect' => 'fade',
      'event' => 'change',
      'method' => 'replace',
    );
  }

  if (isset($form['platform'])) {
    $form['platform']['#prefix'] = '<div id="hosting-site-platform-wrapper">';
    $form['platform']['#suffix'] = '</div>';
  }

  $platform_element = &hosting_site_form_get_widget_element($form, 'platform', array('target_id'));
  if ($platform_element !== NULL) {
    $platform_element['#ajax'] = array(
      'callback' => 'hosting_site_language_callback',
      'wrapper' => 'hosting-site-language-wrapper',
      'effect' => 'fade',
      'event' => 'change',
      'method' => 'replace',
    );
  }

  if (isset($form['language'])) {
    $form['language']['#prefix'] = '<div id="hosting-site-language-wrapper">';
    $form['language']['#suffix'] = '</div>';
  }
}

/**
 * Pass in a site node and return an array of valid options for its fields.
 */
function hosting_site_available_options($node, $platform = NULL) {
  if ($node instanceof \Drupal\Core\Entity\ContentEntityInterface) {
    $legacy = new stdClass();
    $legacy->profile = $node->hasField('profile') ? $node->get('profile')->value : NULL;
    $legacy->platform = $node->hasField('platform') ? $node->get('platform')->target_id : NULL;
    $legacy->language = $node->hasField('language') ? $node->get('language')->value : NULL;
    $legacy->client = $node->hasField('client') ? $node->get('client')->target_id : NULL;
    $node = $legacy;
  }
  elseif (is_array($node)) {
    $node = (object) $node;
  }
  elseif (is_object($node)) {
    $node = clone $node;
  }
  else {
    $node = new stdClass();
  }

  $return = array();

  $return['profile'] = array();
  $return['platform'] = array();
  $return['language'] = array();

  if (!hosting_feature('client')) {
    $return['client'] = NULL;
  }

  $user = user_load($GLOBALS['user']->uid);

  $profiles = hosting_get_profiles();
  $platform_profiles = array();
  foreach ($profiles as $id => $name) {
    $result = \Drupal::database()->query("SELECT l.id
                        FROM {hosting_package_instance} i
                        JOIN {hosting_package} p
                        ON p.id = i.package_id
                        JOIN {hosting_platform} l
                        ON l.id = i.rid
                        WHERE i.package_id = :package_id
                        AND p.package_type = :package_type
                        AND l.status = :status;",
                        array(
                          ':package_id' => $id,
                          ':package_type' => 'profile',
                          ':status' => HOSTING_PLATFORM_ENABLED,
                        )
                      );
    if (is_null($platform)) {
      $allowed_plats = _hosting_get_allowed_platforms($user->uid);
    }
    else {
      $allowed_plats = array($platform => $platform);
    }
    $access_check = FALSE;

    foreach ($result as $row) {
      if (!is_null($platform) && array_key_exists($row->id, $allowed_plats)) {
        $platform_profiles[$id] = $name;
      }
      elseif (array_key_exists($row->id, $allowed_plats)) {
        $access_check = TRUE;
      }
      elseif (!$unrestricted = \Drupal::database()->query("SELECT cid
                                         FROM {hosting_platform_client_access}
                                         WHERE pid = :pid LIMIT 1",
                                         array(':pid' => $row->id)
                                       )->fetchField()) {
        $access_check = TRUE;
      }
    }
    if (!$access_check) {
      unset($profiles[$id]);
    }
  }
  if (!is_null($platform)) {
    $profiles = $platform_profiles;
  }
  reset($profiles);
  $return['profile'] = array_keys($profiles);

  if (!isset($node->profile) && $return['profile']) {
    $node->profile = hosting_get_default_profile($return['profile'][0]);
  }
  elseif (isset($node->profile) && $node->profile && !is_numeric($node->profile)) {
    $package = hosting_get_package($node->profile, 'profile');
    if ($package) {
      $node->profile = $package->id();
    }
  }

  if (!is_null($platform)) {
    $node->profile = $return['profile'][0];
    $return['platform'] = array($platform);
  }
  else {
    $options = array();
    $platforms = hosting_get_profile_platforms($node->profile, isset($node->check_profile_migrations) ? $node->check_profile_migrations : FALSE);
    if (count($platforms)) {
      foreach ($platforms as $platform_id => $title) {
        $platform_entity = \Drupal::entityTypeManager()->getStorage('hosting_platform')->load($platform_id);
        if (!$platform_entity) {
          continue;
        }

        if (hosting_entity_status($platform_entity, 'platform') != HOSTING_PLATFORM_LOCKED || \Drupal::currentUser()->hasPermission('create sites on locked platforms')) {
          $platform_client_ids = hosting_platform_client_ids($platform_entity);
          if (empty($platform_client_ids) || count(array_intersect(array_keys($user->client_id), $platform_client_ids)) || $user->uid == 1) {
            $options[] = $platform_id;
          }
        }
      }

      $return['platform'] = $options;
    }
  }

  if (!isset($node->platform) || !in_array($node->platform, $return['platform'])) {
    $node->platform = $return['platform'][0] ?? NULL;
  }

  $return['language'] = array_keys((array) hosting_get_profile_languages($node->profile, $node->platform));

  \Drupal::moduleHandler()->alter('hosting_site_options', $return, $node);

  return $return;
}

/**
 * Legacy wrapper to build a hosting_site form section.
 */
function hosting_site_form($node, &$form_state) {
  if (!$node instanceof \Drupal\Core\Entity\ContentEntityInterface || $node->getEntityTypeId() !== 'hosting_site') {
    $node = \Drupal::entityTypeManager()->getStorage('hosting_site')->create();
  }

  $form['#entity'] = $node;
  $form['#node'] = $node;
  if (is_object($form_state) && method_exists($form_state, 'set')) {
    $form_state->set('node', $node);
  }
  else {
    $form_state['node'] = $node;
  }

  $form_display = \Drupal::service('entity_display.repository')->getFormDisplay('hosting_site', 'default');
  $form_display->buildForm($node, $form, $form_state);
  hosting_site_apply_form_overrides($form, $form_state, $node);

  return $form;
}

/**
 * Ajax callback for the site profile field.
 */
function hosting_site_platform_callback($form, &$form_state) {
  if (isset($form['platform'])) {
    return $form['platform'];
  }
  if (isset($form['site']['platform'])) {
    return $form['site']['platform'];
  }
  return $form;
}

/**
 * Ajax callback for the site platform field.
 */
function hosting_site_language_callback($form, &$form_state) {
  if (isset($form['language'])) {
    return $form['language'];
  }
  if (isset($form['site']['language'])) {
    return $form['site']['language'];
  }
  return $form;
}

/**
 * Implements hook_validate().
 */
function hosting_site_validate($node, &$form, $form_state = NULL) {
  global $user;
  $valid_options = hosting_site_available_options($node);
  $set_error = function ($name, $message) use ($form_state) {
    if ($form_state instanceof \Drupal\Core\Form\FormStateInterface) {
      $form_state->setErrorByName($name, $message);
    }
    else {
      \Drupal::messenger()->addError($message);
    }
  };

  $url = hosting_site_get_domain(hosting_entity_label($node));

  if (!_hosting_valid_fqdn($url)) {
    $set_error(hosting_site_form_error_name($form, 'label', array('value')), t("You have not specified a valid url for this site."));
  }

  $length = strlen($url);
  if ($length > HOSTING_MAX_ALIAS_LENGTH) {
    $long = $length - HOSTING_MAX_ALIAS_LENGTH;
    $set_error(hosting_site_form_error_name($form, 'label', array('value')), t('The url your provided is @long character(s) too long. Please shorten.', array('@long' => $long)));
  }

  if (isset($node->new_client) && !$node->new_client) {
    $client = hosting_get_client($node->client);
    if (!$node->client || !$client) {
      $set_error(hosting_site_form_error_name($form, 'client', array('target_id')), t('Please fill in a valid client'));
    }
    $client_id = hosting_entity_id($client);
    if (!\Drupal::currentUser()->hasPermission('administer clients') && !array_key_exists($client_id, hosting_get_client_from_user($user->uid))) {
      $set_error(hosting_site_form_error_name($form, 'client', array('target_id')), t('Access denied to client @client', array('@client' => hosting_entity_label($client))));
    }
    $node->client = $client_id;
  }

  if (!hosting_domain_allowed($url, (array) $node)) {
    $set_error(hosting_site_form_error_name($form, 'label', array('value')), t("The domain name you have specified is already in use."));
  }

  if (!hosting_entity_id($node) && function_exists('hosting_site_quota_exceeded')) {
    $quota_error = hosting_site_quota_exceeded($node);
    if ($quota_error) {
      $set_error(hosting_site_form_error_name($form, 'label', array('value')), $quota_error);
    }
  }

  if (!in_array($node->profile, $valid_options['profile']) && !hosting_entity_id($node)) {
    $set_error(hosting_site_form_error_name($form, 'profile', array('value')), t('Please choose a valid profile'));
  }

  if (!in_array($node->platform, $valid_options['platform']) && !hosting_entity_id($node)) {
    $set_error(hosting_site_form_error_name($form, 'platform', array('target_id')), t('Please choose a valid platform'));
  }

  if (!in_array($node->language, $valid_options['language']) && !hosting_entity_id($node)) {
    $set_error(hosting_site_form_error_name($form, 'language', array('value')), t('Please fill in a valid language'));
  }

}

/**
 * Implements hook_form_alter().
 */
function hosting_site_form_alter(&$form, &$form_state, $form_id) {
  if (in_array($form_id, array('hosting_site_form', 'hosting_site_add_form', 'hosting_site_edit_form'), TRUE)) {
    $node = $form['#entity'] ?? $form['#node'] ?? NULL;
    if (isset($form['actions']['delete']) && $node && hosting_entity_status($node, 'site') !== HOSTING_SITE_DELETED) {
      $form['actions']['delete']['#type'] = 'hidden';
    }
  }

  if (array_key_exists('bulk_form', $form)) {
    $form['#after_build'][] = '_hosting_site_collapse_views_fieldset';
  }

  if ($form_id == 'hosting_task_confirm_form' && $form['task']['#value'] == 'delete' && (\Drupal::config('hosting.settings')->get('hosting_delete_force') ?? FALSE)) {
    $form['parameters']['force'] = array(
      '#type' => 'value',
      '#value' => 1,
    );
  }
}
