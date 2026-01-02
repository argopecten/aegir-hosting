<?php

namespace Drupal\hosting_client;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for Hosting client entities.
 */
class HostingClientAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (!hosting_feature('client')) {
      return AccessResult::forbidden();
    }

    if ($account->hasPermission('administer clients')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $client_id = $entity->id();
    $owner_id = $entity->get('uid')->target_id;

    switch ($operation) {
      case 'view':
        if (!$account->hasPermission('view client')) {
          return AccessResult::forbidden();
        }
        $has_access = (bool) \Drupal::database()->query(
          "SELECT 1 FROM {hosting_client_user} WHERE user = :user AND client = :client",
          array(':user' => $account->id(), ':client' => $client_id)
        )->fetchField();
        return AccessResult::allowedIf($has_access);

      case 'update':
        if ($account->hasPermission('edit own client') && $account->id() == $owner_id) {
          return AccessResult::allowed();
        }
        break;

      case 'delete':
        if ($account->hasPermission('delete own client') && $account->id() == $owner_id) {
          return AccessResult::allowed();
        }
        break;
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    if (!hosting_feature('client')) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowedIfHasPermission($account, 'create client');
  }

}
