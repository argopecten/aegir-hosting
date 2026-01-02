<?php

namespace Drupal\hosting\Plugin\views\field;

use Drupal\views\ResultRow;

/**
 * Status field for hosting platforms.
 *
 * @ViewsField("hosting_platform_status")
 */
class HostingPlatformStatusField extends HostingStatusFieldBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    $output = _hosting_platform_status($value);

    return $this->buildStatusOutput($output, _hosting_platform_list_class($value));
  }

}
