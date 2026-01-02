<?php

namespace Drupal\hosting\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Sites count field for hosting platforms.
 *
 * @ViewsField("hosting_platform_sites")
 */
class HostingPlatformSitesField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    return hosting_site_count($value, [HOSTING_SITE_ENABLED, HOSTING_SITE_DISABLED, HOSTING_SITE_QUEUED]);
  }

}
