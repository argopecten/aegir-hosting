<?php

namespace Drupal\hosting\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filter by package type.
 *
 * @ViewsFilter("hosting_package_type_filter")
 */
class HostingPackageTypeFilter extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueTitle = $this->t('Package Type');
      $this->valueOptions = _hosting_package_types();
    }
  }

}
