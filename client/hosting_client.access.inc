<?php
/**
 * @file
 * Control client node access.
 */

/**
 * Implements hook_user_load().
 */
function hosting_client_user_load($users) {
  foreach ($users as $uid => $user) {
    $account_id = method_exists($user, 'id') ? $user->id() : ($user->uid ?? NULL);
    if ($account_id !== NULL) {
      $users[$uid]->client_id = hosting_get_client_from_user((int) $account_id);
    }
  }
}

/**
 * Implements hook_user_view().
 */
function hosting_client_user_view($account, $view_mode) {
  if (hosting_feature('client')) {
    return hosting_client_view_user($account);
  }
}

/**
 * Implements hook_user_presave().
 */
function hosting_client_user_presave(\Drupal\user\UserInterface $account) {
  // Relationships are handled by the user form submit handler.
}

/**
 * Implements hook_user_insert().
 */
function hosting_client_user_insert(\Drupal\user\UserInterface $account) {
  // Relationships are handled by the user form submit handler.
}

/**
 * Implements hook_user_cancel().
 */
function hosting_client_user_cancel($edit, $account, $method) {
  $account_id = $account->id();
  \Drupal::database()->delete('hosting_client_user')
    ->condition('user', (int) $account_id)
    ->execute();
}

/**
 * Add visible items when viewing a user().
 *
 * @see hosting_client_user()
 */
function hosting_client_view_user($user) {
  if ($user->client_id) {

    foreach ($user->client_id as $client => $type) {
      $rows[] = array(_hosting_node_link($client));
    }

    // This is a table because we'll have types for clients eventually.
    $header = array(t('Hosting client'));
    $items['client_list'] = array(
      'value' => \Drupal::service('theme.manager')->render('table', array('header' => $header, 'rows' => $rows)),
      'class' => 'client',
    );

    return array(t('Clients') => $items);
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 *
 * Add items to the user forms when editing or registering.
 *
 * @see hosting_client_user()
 */
function hosting_client_form_user_profile_form_alter(&$form, &$form_state, $form_id) {
  if ($form['#user_category'] == 'account') {
    $user = $form['#user'];
    $account_id = method_exists($user, 'id') ? $user->id() : ($user->uid ?? NULL);

    // In Aegir 3.x the admin account does not have the client_id array and sometimes a normal user
    // is missing it, as well.  If it isn't on the user object, add it.
    if (!is_array($user->client_id)) {
      if ($account_id !== NULL) {
        $user->client_id = hosting_get_client_from_user((int) $account_id);
      }
    }

    $clients = array();
    if ($user->client_id) {
      foreach ($user->client_id as $client => $type) {
        $clients[$client] = '';
        $form['client_edit']['name'][$client] = array(
          '#type' => 'markup',
          '#value' => _hosting_node_link($client),
        );
      }
    }
    if (\Drupal::currentUser()->hasPermission('edit client users')) {
      $form['client_edit']['clients'] = array(
        '#type' => 'checkboxes',
        '#options' => $clients,
      );
    }

    $form['client_edit']['header'] = array(
      '#type' => 'value',
      '#value' => array(array('data' => t('Accessible clients')), array('data' => t('Remove'))),
    );

    if (\Drupal::currentUser()->hasPermission('edit client users')) {
      $form['client_edit']['hosting_client'] = array(
        '#type' => 'textfield',
        '#title' => t('Associate a client to this user'),
        '#weight' => 2,
        '#autocomplete_path' => 'hosting_client/autocomplete/client',
        '#description' => t('This field allows you to associate an existing client to a user.
                             It does not create a new client, but allows this user
                             to manage sites belonging to the given client.'),
      );
    }
    $form['client_edit']['#theme'] = 'hosting_client_user_form';
    $form['#validate'][] = 'hosting_client_user_form_validate';
    $form['#submit'][] = 'hosting_client_user_form_submit';
  }
}

/**
 * Returns HTML for the client area on the user form.
 */
function theme_hosting_client_user_form($variables) {
  $form = $variables['form'];
  if (array_key_exists('hosting_client', $form)) {
    $edit_name = \Drupal::service('renderer')->render($form['hosting_client']);
  }
  else {
    $edit_name = '';
  }

  $rows = array();
  if (isset($form['name'])) {
    foreach (element_children($form['name'], TRUE) as $client) {
      $row = array();
      $row['data'][] = $form['name'][$client]['#value'];
      if (\Drupal::currentUser()->hasPermission('edit client users')) {
        $row['data'][] = \Drupal::service('renderer')->render($form['clients'][$client]);
      }
      $rows[] = $row;
    }
  }

  $output = \Drupal::service('renderer')->render($form);
  $output .= \Drupal::service('theme.manager')->render('table', array('header' => $form['header']['#value'], 'rows' => $rows));
  $output .= $edit_name;
  return $output;
}

/**
 * Validate the submission of a user form.
 *
 * @see hosting_client_user()
 */
function hosting_client_user_form_validate($form, \Drupal\Core\Form\FormStateInterface $form_state) {
  $client_id = $form_state->getValue(['client_edit', 'hosting_client']) ?? $form_state->getValue('hosting_client');
  if ($client_id && (!$client = hosting_get_client($client_id))) {
    $form_state->setErrorByName('hosting_client', 'Please fill in a valid client');
  }
}

/**
 * Save the values submitted when editing or adding a user.
 *
 * @see hosting_client_user()
 */
function hosting_client_user_form_submit($form, $form_state = NULL) {
  if ($form_state instanceof \Drupal\Core\Form\FormStateInterface) {
    $edit = [];
    $clients = $form_state->getValue(['client_edit', 'clients']) ?? $form_state->getValue('clients');
    if (is_array($clients)) {
      $edit['clients'] = $clients;
    }
    $hosting_client = $form_state->getValue(['client_edit', 'hosting_client']) ?? $form_state->getValue('hosting_client');
    if ($hosting_client) {
      $edit['hosting_client'] = $hosting_client;
    }
    $user = $form['#user'] ?? ($form_state->getFormObject() ? $form_state->getFormObject()->getEntity() : NULL);
  }
  else {
    $edit = is_array($form) ? $form : [];
    $user = $form_state;
  }

  if (!$user instanceof \Drupal\user\UserInterface) {
    return;
  }
  $account_id = $user->id();
  if (array_key_exists('clients', $edit)) {
    foreach ($edit['clients'] as $client) {
      if (!$client) {
        continue;
      }
      \Drupal::database()->delete('hosting_client_user')
        ->condition('user', (int) $account_id)
        ->condition('client', $client)
        ->execute();
    }
  }
  if (!empty($edit['hosting_client'])) {
    $client = hosting_get_client($edit['hosting_client']);
    \Drupal::database()->insert('hosting_client_user')
      ->fields(array(
        'client' => hosting_entity_id($client),
        'user' => (int) $account_id,
        'contact_type' => '',
      ))
      ->execute();
  }
}


/**
 * Get relationships a user has with different clients.
 *
 * @param int $uid
 *   The user to get the relationships for.
 *
 * @return array
 *   An array of clients and their contact type relationships to the specified
 *   user.
 */
function hosting_get_client_from_user($uid) {
  $clients = array();
  if ($results = \Drupal::database()->query("SELECT client, contact_type FROM {hosting_client_user} WHERE user = :user", array(':user' => $uid))) {
    foreach ($results as $result) {
      $clients[$result->client] = explode(',', $result->contact_type);
    }
  }
  return $clients;
}
