<?php

namespace Drupal\hosting_server;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for Hosting server entities.
 */
class HostingServerAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $use_client_access = \Drupal::config('hosting_server.settings')
      ->get('hosting_server_use_hosting_client_access') ?? TRUE;

    if ($account->hasPermission('administer servers')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view server');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit server');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete server');
    }

    if ($use_client_access && hosting_feature('client') && $operation !== 'create') {
      return AccessResult::neutral();
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'create server');
  }

}
