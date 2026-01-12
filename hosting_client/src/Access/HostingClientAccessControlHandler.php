<?php

namespace Drupal\hosting_client\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

class HostingClientAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission('administer clients')) {
      return AccessResult::allowed();
    }

    $is_owner = (int) $entity->get('owner')->target_id === (int) $account->id();

    return match ($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view client'),
      'update' => $is_owner ? AccessResult::allowedIfHasPermission($account, 'edit own client') : AccessResult::neutral(),
      'delete' => $is_owner ? AccessResult::allowedIfHasPermission($account, 'delete own client') : AccessResult::neutral(),
      default => AccessResult::neutral(),
    };
  }

  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    if ($account->hasPermission('administer clients')) {
      return AccessResult::allowed();
    }
    return AccessResult::allowedIfHasPermission($account, 'create client');
  }

}
