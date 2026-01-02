<?php
/**
 * @file
 * General IP address CRUD routines.
 */

/**
 * Load IP addresses for a server.
 */
function hosting_server_ip_addresses($server) {
  $server_id = hosting_entity_id($server);
  if (!$server_id) {
    return array();
  }
  $ip_list = array();
  $result = \Drupal::database()->query("SELECT id, ip_address FROM {hosting_ip_addresses} WHERE server_id = :server_id ORDER BY id", array(':server_id' => $server_id));
  while ($obj = $result->fetch()) {
    $ip_list[$obj->id] = $obj->ip_address;
  }
  return $ip_list;
}

/**
 * Load IP address values for a server.
 */
function hosting_server_ip_address_values($server) {
  if ($server instanceof \Drupal\Core\Entity\ContentEntityInterface && $server->hasField('ip_address_list')) {
    $values = $server->get('ip_address_list')->getValue();
    return array_values(array_map(static function ($item) {
      return $item['value'];
    }, $values));
  }
  return array_values(hosting_server_ip_addresses($server));
}

/**
 * Display the ip address on the node. IP utility function for hook_view.
 */
function _hosting_ip_view(&$node) {
  $ip_list = isset($node->ip_addresses) ? $node->ip_addresses : hosting_server_ip_addresses($node);

  if (sizeof($ip_list)) {
    $node->content['info']['ip_addresses'] = array(
      '#type' => 'item',
      '#title' => t('IP addresses'),
      '#markup' => implode('<br />', $ip_list),
    );
  }
}

/**
 * IP Utility function for hook_update/hook_insert.
 */
function _hosting_ip_save($node, $update = FALSE) {
  $ips = isset($node->ip_addresses) ? (array) $node->ip_addresses : hosting_server_ip_addresses($node);
  $server_id = hosting_entity_id($node);

  foreach ($ips as $id => $ip) {
    $ip = trim($ip);
    if (empty($ip)) {
      // empty fields are considered removed
      \Drupal::database()->delete('hosting_ip_addresses')
        ->condition('id', $id)
        ->execute();
    }
    else {
      $num_updated = \Drupal::database()->update('hosting_ip_addresses')
        ->fields(array(
          'ip_address' => $ip,
        ))
        ->condition('id', $id)
        ->execute();
    }
  }

  $ips = isset($node->new_ip_addresses) ? (array) $node->new_ip_addresses : array();
  foreach ($ips as $id => $ip) {
    if (!empty($ip)) {
      // new entries are prefixed with the string 'new', insert those
      \Drupal::database()->insert('hosting_ip_addresses')
        ->fields(array(
          'server_id' => $server_id,
          'ip_address' => $ip,
        ))
        ->execute();
    }
  }
}

/**
 * Validate that IP list is valid. IP utility function for hook_validate.
 */
function _hosting_ip_validate($node, $form_state = NULL) {
  $set_error = function ($name, $message) use ($form_state) {
    if ($form_state instanceof \Drupal\Core\Form\FormStateInterface) {
      $form_state->setErrorByName($name, $message);
    }
    else {
      \Drupal::messenger()->addError($message);
    }
  };
  $ips = isset($node->ip_addresses) ? $node->ip_addresses : hosting_server_ip_addresses($node);
  foreach ($ips as $id => $ip) {
    $ip = trim($ip);
    if (empty($ip)) {
      // deletion, look if the IP is in use
      $cid = \Drupal::database()->query('SELECT ssl_key FROM {hosting_ssl_cert_ips} i INNER JOIN {hosting_ssl_cert} c ON c.cid = i.cid WHERE ip_address = :id', array(':id' => $id))->fetchField();
      if ($cid) {
        $set_error("ip_addresses][$id", t('Cannot remove IP associated with certificate %cert.', array('%cert' => $cid)));
      }
    }
    elseif (!_hosting_valid_ip($ip)) {
      $set_error("ip_addresses][$id", t('Invalid IP address: %ip.', array(
        '%ip' => $ip )));
    }
  }

  $ips = isset($node->new_ip_addresses) ? (array) $node->new_ip_addresses : array();
  foreach ($ips as $id => $ip) {
    if (!empty($ip) && !_hosting_valid_ip($ip)) {
      $set_error("ip_addresses][$id", t('Invalid IP address: %ip.', array(
        '%ip' => $ip )));
    }
  }
}

/**
 * IP Utility function for hook_load.
 */
function _hosting_ip_load($node) {
  return hosting_server_ip_addresses($node);
}

/**
 * IP Utility function for hook_delete.
 */
function _hosting_ip_delete($node) {
  $server_id = hosting_entity_id($node);
  \Drupal::database()->delete('hosting_ip_addresses')
    ->condition('server_id', $server_id)
    ->execute();
}


/**
 * Allocate an IP for a given site on a given server.
 */
function hosting_ip_allocate($cert, $site) {
  // make sure the IP is not allocated while we pick ours
  \Drupal::database()->query("LOCK TABLES {hosting_ssl_cert_ips} WRITE, {hosting_ip_addresses} WRITE");
  $platform_id = $site instanceof \Drupal\Core\Entity\ContentEntityInterface ? $site->get('platform')->target_id : (isset($site->platform) ? $site->platform : NULL);
  $platform = $platform_id ? \Drupal::entityTypeManager()->getStorage('hosting_platform')->load($platform_id) : NULL;
  $server_id = NULL;
  if ($platform instanceof \Drupal\Core\Entity\ContentEntityInterface) {
    $server_id = $platform->get('web_server')->target_id;
  }
  elseif (is_object($platform) && isset($platform->web_server)) {
    $server_id = $platform->web_server;
  }
  $server = $server_id ? \Drupal::entityTypeManager()->getStorage('hosting_server')->load($server_id) : NULL;
  if (!$server) {
    \Drupal::database()->query("UNLOCK TABLES");
    return FALSE;
  }

  // IP allocation for master server
  $ips = array();

  // IP allocation for nodes in the cluster
  // XXX: this should be in classes, see below
  $http_service = hosting_server_service($server, 'http');
  $http_type = $http_service ? $http_service->type : NULL;
  switch ($http_type) {
    case 'pack':
      foreach ($http_service->master_servers as $s) {
        $ips[] = hosting_server_ip_allocate($s);
      }
      foreach ($http_service->slave_servers as $s) {
        $ips[] = hosting_server_ip_allocate($s);
      }
      break;
    case 'cluster':
      foreach ($http_service->web_servers as $s) {
        $ips[] = hosting_server_ip_allocate($s);
      }
      break;
    default:
      $ips = array(hosting_server_ip_allocate($server->id()));
  }
  foreach ($ips as $ip) {
    if (!$ip) {
      // IP allocation failed, unlock tables and return false
      \Drupal::database()->query("UNLOCK TABLES");
      return $ip;
    }
  }
  // IP allocation succeeded, actually insert all required entries
  foreach ($ips as $ip) {
    if ($ip) {
     $id = \Drupal::database()->insert('hosting_ssl_cert_ips')
      ->fields(array(
        'cid' => $cert->cid,
        'ip_address' => $ip,
      ))
      ->execute();
    }
  }
  \Drupal::database()->query("UNLOCK TABLES");
  return $ips;
}

/**
 * Allocate a single IP for a single server.
 *
 * XXX: this should be in the 'server' class (which doesn't exist yet)
 * or at least in the services class
 *
 * @param int $server
 *   The server node ID.
 *
 * @return int
 *   Id of the next available IP.
 */
function hosting_server_ip_allocate($server) {
  // guess the next available IP
  return \Drupal::database()->query("SELECT hosting_ip_addresses.id
                   FROM {hosting_ip_addresses}
                     LEFT JOIN {hosting_ssl_cert_ips}
                       ON (hosting_ip_addresses.id = hosting_ssl_cert_ips.ip_address)
                   WHERE hosting_ssl_cert_ips.ip_address IS NULL
                     AND server_id = :server_id LIMIT 1;",
                   array(':server_id' => $server)
                 )->fetchField();
}
