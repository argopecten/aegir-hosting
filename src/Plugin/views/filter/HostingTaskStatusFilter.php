<?php

namespace Drupal\hosting\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filter by task status.
 *
 * @ViewsFilter("hosting_task_status_filter")
 */
class HostingTaskStatusFilter extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueTitle = $this->t('Task Status');
      $this->valueOptions = _hosting_task_error_codes();
    }
  }

}
