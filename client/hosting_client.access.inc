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
    $users[$uid]->client_id = hosting_get_client_from_user($user->uid);
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
 * Implements hook_user_XXX().
 */
function hosting_client_user_XXX($edit, $account) {
  if (hosting_feature('client')) {
    return hosting_client_user_form($edit, $account, $category);
  }
}

/**
 * Implements hook_user_presave().
 */
function hosting_client_user_presave(&$edit, $account, $category) {
  return hosting_client_user_form_validate($edit);
}

/**
 * Implements hook_user_insert().
 */
function hosting_client_user_insert(&$edit, $account, $category) {
  hosting_client_user_form_submit($edit, $account);
  $edit['hosting_client'] = NULL;
}

/**
 * Implements hook_user_cancel().
 */
function hosting_client_user_cancel($edit, $account, $method) {
  db_delete('hosting_client_user')
    ->condition('user', $account->uid)
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
      'value' => theme('table', array('header' => $header, 'rows' => $rows)),
      'class' => 'client',
    );

    return array(t('Clients') => $items);
  }
}

/**
 * Add items to the user forms when editing or registering.
 *
 * @see hosting_client_user()
 */
function hosting_client_user_form($edit, $user, $category) {

  $clients = array();
  if ($user->client_id) {
    foreach ($user->client_id as $client => $type) {
      $clients[$client] = '';
      $fields[$category]['name'][$client] = array(
        '#type' => 'markup',
        '#value' => _hosting_node_link($client),
      );
    }
  }
  if (user_access('edit client users')) {
    $fields[$category]['clients'] = array(
      '#type' => 'checkboxes',
      '#options' => $clients,
    );
  }

  $fields[$category]['header'] = array(
    '#type' => 'value',
    '#value' => array(array('data' => t('Accessible clients')), array('data' => t('Remove'))),
  );

  if (user_access('edit client users')) {
    $fields[$category]['hosting_client'] = array(
      '#type' => 'textfield',
      '#title' => t('Associate a client to this user'),
      '#weight' => 2,
      '#autocomplete_path' => 'hosting_client/autocomplete/client',
      '#description' => t('This field allows you to associate an existing client to a user.
                             It does not create a new client, but allows this user
                             to manage sites belonging to the given client.'),
    );
  }
  $fields[$category]['#theme'] = 'hosting_client_user_form';
  return $fields;
}

/**
 * Returns HTML for the client area on the user form.
 */
function theme_hosting_client_user_form($variables) {
  $form = $variables['form'];
  if (array_key_exists('hosting_client', $form)) {
    $edit_name = drupal_render($form['hosting_client']);
  }
  else {
    $edit_name = '';
  }
  foreach (element_children($form['name']) as $client) {
    $row = array();
    $row['data'][] = drupal_render($form['name'][$client]);
    if (user_access('edit client users')) {
      $row['data'][] = drupal_render($form['clients'][$client]);
    }
    $rows[] = $row;
  }
  $output = drupal_render_children($form);
  $output .= theme('table', array('header' => $form['header']['#value'], 'rows' => $rows));
  $output .= $edit_name;
  return $output;
}

/**
 * Validate the submission of a user form.
 *
 * @see hosting_client_user()
 */
function hosting_client_user_form_validate($edit) {
  if (array_key_exists('hosting_client', $edit) && $edit['hosting_client'] && (!$client = hosting_get_client($edit['hosting_client']))) {
    form_set_error('hosting_client', 'Please fill in a valid client');
  }
}

/**
 * Save the values submitted when editing or adding a user.
 *
 * @see hosting_client_user()
 */
function hosting_client_user_form_submit($edit, $user) {
  if (array_key_exists('clients', $edit)) {
    foreach ($edit['clients'] as $client) {
      $query = db_delete('hosting_client_user')
        ->condition('user', $user->uid)
        ->condition('client', $client)
        ->execute();
    }
  }
  if (array_key_exists('hosting_client', $edit) && $edit['hosting_client']) {
    $client = hosting_get_client($edit['hosting_client']);
    $query = $id = db_insert('hosting_client_user')
      ->fields(array(
        'client' => $client->nid,
        'user' => $user->uid,
        'contact_type' => '',
      ))
      ->execute();
  }
}

/**
 * Function to make sure we don't respond with grants when disabling ourselves.
 */
function hosting_client_disabling($set = NULL) {
  static $disabling = FALSE;
  if ($set !== NULL) {
    $disabling = $set;
  }
  return $disabling;
}

/**
 * Implements hook_node_grants().
 */
function hosting_client_node_grants($account, $op) {
  $grants = &drupal_static(__FUNCTION__);
  if (!isset($grants)) {
    $grants = array();
  }
  $cache_id = $account->uid . $op;
  if (!isset($grants[$cache_id])) {
    $account->client_id = hosting_get_client_from_user($account->uid);
    $types = hosting_feature_node_types();

    foreach ($types as $type) {
      if (user_access('administer ' . $type . 's', $account)) {
        $grants[$cache_id]['hosting ' . $type] = array(1);
      }
      elseif (user_access($op . ' ' . $type, $account)) {
        // TODO: restrict access to certain op-type based on the user relationship to this client - see content of $client_relations
        $grants[$cache_id]['hosting ' . $type] = array_keys($account->client_id);
      }
      elseif ($op == 'update' && user_access('edit ' . $type, $account)) {
        $grants[$cache_id]['hosting ' . $type] = array_keys($account->client_id);
      }
    }
  }
  return isset($grants[$cache_id]) ? $grants[$cache_id] : array();
}

/**
 * Implements hook_node_access_records().
 */
function hosting_client_node_access_records($node) {
  if (hosting_client_disabling()) {
    return;
  }
  $grants = array();
  $base_grant = array(
    'realm' => 'hosting ' . $node->type,
    'grant_view' => 1,
    'grant_update' => 1,
    'grant_delete' => 0,
    'priority' => 1,
  );

  // Tasks inherit from their parent.
  if ($node->type == 'task') {
    $node = node_load($node->rid);
    $base_grant['grant_update'] = 0;
  }

  switch ($node->type) {
    case 'site':
      $base_grant['gid'] = $node->client;
      break;

    case 'client':
      $base_grant['gid'] = $node->nid;
      break;

    case 'package':
      $base_grant['grant_update'] = 0;
      break;

    case 'task':
      // The rest of the node types are configuration, so only admin should see them.
      $base_grant['gid'] = HOSTING_ADMIN_CLIENT;
      break;

    case 'server':
      // The rest of the node types are configuration, so only admin should see them.
      $base_grant['gid'] = HOSTING_ADMIN_CLIENT;

      // Lookup platforms on this server.
      $platforms = _hosting_get_platforms($node->nid);

      $gids = array();
      foreach ($platforms as $nid => $title) {
        $node = node_load($nid);

        if (!empty($node->clients)) {
          foreach ($node->clients as $cid => $client) {
            if ($cid != HOSTING_ADMIN_CLIENT) {
              $gids[] = $cid;
            }
          }
        }
      }

      // Lookup databases hosted on this server.
      $databases = hosting_get_sites_on_db_server($node->nid);
      foreach ($databases as $nid => $dbname) {
        $node = node_load($nid);
        if (!empty($node->client)) {
          if ($node->client != HOSTING_ADMIN_CLIENT) {
            $gids[] = $node->client;
          }
        }
      }
      $gids = array_unique($gids);

      // Grant access to all clients who have access to a platform.
      foreach ($gids as $cid) {
        $grants[] = array_merge($base_grant, array('gid' => $cid, 'grant_update' => 0));
      }
      break;

    case 'platform':
      $base_grant['gid'] = HOSTING_ADMIN_CLIENT;
      if (isset($node->clients)) {
        foreach ($node->clients as $cid => $client) {
          if ($cid != HOSTING_ADMIN_CLIENT) {
            $grants[] = array_merge($base_grant, array('gid' => $cid, 'grant_update' => 0));
          }
        }
      }
      break;

    default:
      // Not hosting node, don't change access.
      return;
  }

  if (isset($base_grant['gid'])) {
    $grants[] = $base_grant;

    // This should _always_ happen.
    if ($base_grant['gid'] != HOSTING_ADMIN_CLIENT) {
      // Also give full access to the administrator user.
      $base_grant['gid'] = HOSTING_ADMIN_CLIENT;
      $grants[] = $base_grant;
    }
    return $grants;
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
  if ($results = db_query("SELECT client, contact_type FROM {hosting_client_user} WHERE user = :user", array(':user' => $uid))) {
    foreach ($results as $result) {
      $clients[$result->client] = explode(',', $result->contact_type);
    }
  }
  return $clients;
}
