<?php

namespace Drupal\hosting\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filter by package status.
 *
 * @ViewsFilter("hosting_package_status_filter")
 */
class HostingPackageStatusFilter extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueTitle = $this->t('Package Status');
      $this->valueOptions = ['1' => $this->t('Enabled'), '0' => $this->t('Disabled')];
    }
  }

}
