<?php

namespace Drupal\hosting_task;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for Hosting task entities.
 */
class HostingTaskAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (hosting_feature('client')) {
      return AccessResult::neutral();
    }

    if ($account->hasPermission('administer tasks')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    if (hosting_feature('client')) {
      return AccessResult::neutral();
    }
    return AccessResult::allowedIfHasPermission($account, 'administer tasks');
  }

}
