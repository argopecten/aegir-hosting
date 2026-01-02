<?php

namespace Drupal\hosting\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filter by task type.
 *
 * @ViewsFilter("hosting_task_type_filter")
 */
class HostingTaskTypeFilter extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueTitle = $this->t('Task Type');
      $options = [];

      foreach (['site', 'platform', 'server'] as $type) {
        $tasks = hosting_available_tasks($type);
        if (!empty($tasks)) {
          foreach ($tasks as $identifier => $task) {
            $options[$identifier] = $task['title'];
          }
        }
      }

      $this->valueOptions = $options;
    }
  }

}
