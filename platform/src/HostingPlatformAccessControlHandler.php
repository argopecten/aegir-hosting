<?php

namespace Drupal\hosting_platform;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for Hosting platform entities.
 */
class HostingPlatformAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('administer platforms')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if (hosting_feature('client') && $operation !== 'create' && $operation !== 'view') {
      return AccessResult::neutral();
    }

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view platform');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit platform');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete platform');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'create platform');
  }

}
