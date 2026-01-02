<?php

namespace Drupal\hosting\Plugin\views\field;

use Drupal\views\ResultRow;

/**
 * Status field for hosting packages.
 *
 * @ViewsField("hosting_package_status")
 */
class HostingPackageStatusField extends HostingStatusFieldBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = (int) $this->getValue($values);
    $output = $value ? $this->t('Enabled') : $this->t('Disabled');
    $class = $value ? 'hosting-success' : 'hosting-info';

    return $this->buildStatusOutput($output, $class);
  }

}
