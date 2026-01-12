<?php

namespace Drupal\hosting_client\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

class HostingClientUserAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission('administer clients')) {
      return AccessResult::allowed();
    }

    return match ($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view client'),
      'update', 'delete' => AccessResult::allowedIfHasPermission($account, 'edit client users'),
      default => AccessResult::neutral(),
    };
  }

  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    if ($account->hasPermission('administer clients')) {
      return AccessResult::allowed();
    }
    return AccessResult::allowedIfHasPermission($account, 'edit client users');
  }

}
