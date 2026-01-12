<?php

namespace Drupal\hosting_platform\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\hosting_platform\Entity\HostingPlatform;

class HostingPlatformAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission('administer platforms')) {
      return AccessResult::allowed();
    }

    return match ($operation) {
      'view' => $this->checkViewAccess($entity, $account),
      'update' => AccessResult::allowedIfHasPermission($account, 'edit platform'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete platform'),
      default => AccessResult::neutral(),
    };
  }

  protected function checkViewAccess(EntityInterface $entity, AccountInterface $account): AccessResult {
    if (!$account->hasPermission('view platform')) {
      return AccessResult::neutral();
    }

    $status = (int) $entity->get('status')->value;
    if ($status === HostingPlatform::STATUS_LOCKED) {
      return AccessResult::allowedIfHasPermission($account, 'view locked platforms');
    }

    return AccessResult::allowed();
  }

  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    if ($account->hasPermission('administer platforms')) {
      return AccessResult::allowed();
    }

    return AccessResult::allowedIfHasPermission($account, 'create platform');
  }

}
