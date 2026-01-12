<?php

namespace Drupal\hosting_site\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

class HostingSiteAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission('administer sites')) {
      return AccessResult::allowed();
    }

    $client_id = (int) $entity->get('client')->target_id;
    $client_access = TRUE;
    if ($client_id && \Drupal::moduleHandler()->moduleExists('hosting_client')) {
      $client_access = \Drupal::service('hosting_client.access_manager')->userHasClient($account, $client_id);
    }

    return match ($operation) {
      'view' => $this->checkClientPermission($account, 'view site', $client_access),
      'update' => $this->checkClientPermission($account, 'edit site', $client_access),
      'delete' => $this->checkClientPermission($account, 'delete site', $client_access),
      default => AccessResult::neutral(),
    };
  }

  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    if ($account->hasPermission('administer sites')) {
      return AccessResult::allowed();
    }

    return AccessResult::allowedIfHasPermission($account, 'create site');
  }

  protected function checkClientPermission(AccountInterface $account, string $permission, bool $client_access): AccessResult {
    if (!$client_access) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowedIfHasPermission($account, $permission);
  }

}
