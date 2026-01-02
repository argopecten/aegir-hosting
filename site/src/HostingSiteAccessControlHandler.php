<?php

namespace Drupal\hosting_site;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for Hosting site entities.
 */
class HostingSiteAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (hosting_feature('client') && $operation !== 'create') {
      return AccessResult::neutral();
    }

    if ($account->hasPermission('administer sites')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view site');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit site');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete site');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'create site');
  }

}
