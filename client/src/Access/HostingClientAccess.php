<?php

namespace Drupal\hosting_client\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\hosting_client\Entity\HostingClient;
use Drupal\hosting_platform\Entity\HostingPlatform;

class HostingClientAccess {
  /**
   * Access check for client configuration.
   */
  public static function configureAccess(AccountInterface $account) {
    $allowed = $account->hasPermission('administer clients') && hosting_feature('client');
    $result = AccessResult::allowedIf($allowed)->addCacheContexts(['user.permissions']);
    return $result->addCacheableDependency(\Drupal::config('hosting.features'));
  }

  /**
   * Access check for adding sites under a client.
   */
  public static function siteAddAccess(HostingClient $hosting_client, AccountInterface $account) {
    $access = $hosting_client->access('view', NULL, TRUE);
    $access = $access->andIf(AccessResult::allowedIf($account->hasPermission('create site')));
    return $access->addCacheContexts(['user.permissions']);
  }

  /**
   * Access check for the platform client access form.
   */
  public static function platformAccess(HostingPlatform $hosting_platform, AccountInterface $account) {
    $status_ok = hosting_entity_status($hosting_platform, 'platform') !== HOSTING_PLATFORM_DELETED;
    $access = $hosting_platform->access('update', NULL, TRUE);
    $access = $access->andIf(AccessResult::allowedIf($status_ok));
    $access = $access->andIf(AccessResult::allowedIf($account->hasPermission('administer clients')));
    return $access->addCacheContexts(['user.permissions'])->addCacheableDependency($hosting_platform);
  }

}
