<?php

namespace Drupal\hosting_platform\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\hosting_site\Entity\HostingSite;

class HostingPlatformController extends ControllerBase {
  /**
   * Render the platform edit form from a site page.
   */
  public function editOnSitePage(HostingSite $hosting_site) {
    $platform_id = $hosting_site->get('platform')->target_id;
    $platform = $platform_id ? \Drupal::entityTypeManager()->getStorage('hosting_platform')->load($platform_id) : NULL;
    if (!$platform) {
      return ['#markup' => $this->t('Platform not found.')];
    }

    return $this->entityFormBuilder()->getForm($platform, 'edit');
  }

}
