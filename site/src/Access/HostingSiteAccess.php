<?php

namespace Drupal\hosting_site\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\hosting_platform\Entity\HostingPlatform;

class HostingSiteAccess {
  /**
   * Access check for adding a site from a platform page.
   */
  public static function addByPlatformAccess(HostingPlatform $hosting_platform, AccountInterface $account) {
    $platform_enabled = (int) $hosting_platform->get('status')->value === HOSTING_PLATFORM_ENABLED;
    $create_access = \Drupal::entityTypeManager()
      ->getAccessControlHandler('hosting_site')
      ->createAccess(NULL, $account, [], TRUE);

    $access = $create_access->andIf(AccessResult::allowedIf($platform_enabled));
    return $access->addCacheableDependency($hosting_platform);
  }

}
