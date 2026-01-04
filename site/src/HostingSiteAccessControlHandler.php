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
    if ($account->hasPermission('administer sites')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $permission_map = [
      'view' => 'view site',
      'update' => 'edit site',
      'delete' => 'delete site',
    ];
    if (!isset($permission_map[$operation])) {
      return AccessResult::neutral();
    }

    $access = AccessResult::allowedIfHasPermission($account, $permission_map[$operation]);
    if (hosting_feature('client') && $operation !== 'create') {
      $access = $access->andIf($this->checkClientAccess($entity, $account));
    }

    return $access;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'create site');
  }

  /**
   * Enforce client ownership access when the client feature is enabled.
   */
  private function checkClientAccess(EntityInterface $entity, AccountInterface $account): AccessResult {
    if ($account->hasPermission('administer clients')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    if (!$entity->hasField('client')) {
      return AccessResult::neutral();
    }
    $client_id = $entity->get('client')->target_id;
    if (!$client_id) {
      return AccessResult::allowed()->cachePerUser();
    }

    $has_access = (bool) \Drupal::database()->query(
      "SELECT 1 FROM {hosting_client_user} WHERE user = :user AND client = :client",
      [':user' => $account->id(), ':client' => $client_id]
    )->fetchField();

    return AccessResult::allowedIf($has_access)->cachePerUser();
  }

}
