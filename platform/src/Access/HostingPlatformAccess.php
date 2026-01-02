<?php

namespace Drupal\hosting_platform\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\hosting_site\Entity\HostingSite;

class HostingPlatformAccess {
  /**
   * Access check for platform edit on a site page.
   */
  public static function editOnSitePageAccess(HostingSite $hosting_site, AccountInterface $account) {
    if (hosting_entity_task_scope($hosting_site) !== 'site') {
      return AccessResult::neutral();
    }
    $platform_id = $hosting_site->get('platform')->target_id;
    $platform = $platform_id ? \Drupal::entityTypeManager()->getStorage('hosting_platform')->load($platform_id) : NULL;
    if (!$platform) {
      return AccessResult::forbidden();
    }

    return $platform->access('update', $account, TRUE);
  }

}
