<?php

namespace Drupal\hosting\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filter by database server type.
 *
 * @ViewsFilter("hosting_db_server_type_filter")
 */
class HostingDbServerTypeFilter extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueTitle = $this->t('DB Server Type');
      $options = [];
      $result = \Drupal::database()->query('SELECT DISTINCT(db_type) FROM {hosting_db_server}');
      foreach ($result as $db_server) {
        $options[$db_server->db_type] = $db_server->db_type;
      }
      $this->valueOptions = $options;
    }
  }

}
