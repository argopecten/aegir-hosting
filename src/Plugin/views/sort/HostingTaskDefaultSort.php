<?php

namespace Drupal\hosting\Plugin\views\sort;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Default sort handler for hosting tasks.
 *
 * @ViewsSort("hosting_task_default_sort")
 */
class HostingTaskDefaultSort extends SortPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $task_alias = $this->ensureMyTable();

    $this->query->addOrderBy(NULL, "FIELD({$task_alias}.task_status, 0, -1, '>0')", 'ASC', 'hosting_task_default');
    $this->query->addOrderBy($task_alias, 'executed', 'DESC');
    $this->query->addOrderBy($task_alias, 'created', 'DESC');
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['order']['#access'] = FALSE;
  }

}
