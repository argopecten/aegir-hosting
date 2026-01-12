<?php

namespace Drupal\hosting_server\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

class HostingServerAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission('administer servers')) {
      return AccessResult::allowed();
    }

    return match ($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view server'),
      'update' => AccessResult::allowedIfHasPermission($account, 'edit server'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete server'),
      default => AccessResult::neutral(),
    };
  }

  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    if ($account->hasPermission('administer servers')) {
      return AccessResult::allowed();
    }
    return AccessResult::allowedIfHasPermission($account, 'create server');
  }

}
