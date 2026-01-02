<?php

namespace Drupal\hosting\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Sort handler for hosting package site counts.
 *
 * @ViewsSort("hosting_package_site_count_sort")
 */
class HostingPackageSiteCountSort extends SortPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $platform = $this->view->args[0] ?? 0;

    $expression = sprintf(
      '(SELECT COUNT(iid) FROM {hosting_package_instance} pi WHERE pi.package_id = %s.package_id AND pi.platform = :hosting_platform AND pi.status = 1)',
      $this->tableAlias
    );

    $alias = $this->query->addExpression($expression, 'hosting_package_site_count', [
      ':hosting_platform' => (int) $platform,
    ]);

    $this->query->addOrderBy(NULL, $alias, $this->options['order'] ?? 'ASC');
  }

}
