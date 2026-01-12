<?php

namespace Drupal\hosting_site\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

class HostingSiteBackupAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission('administer sites')) {
      return AccessResult::allowed();
    }

    return match ($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view site'),
      'update' => AccessResult::allowedIfHasPermission($account, 'edit site'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete site'),
      default => AccessResult::neutral(),
    };
  }

  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    if ($account->hasPermission('administer sites')) {
      return AccessResult::allowed();
    }

    return AccessResult::allowedIfHasPermission($account, 'create site');
  }

}
