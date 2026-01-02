<?php

namespace Drupal\hosting\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Site count field for hosting packages.
 *
 * @ViewsField("hosting_package_site_count")
 */
class HostingPackageSiteCountField extends FieldPluginBase {

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

    $this->fieldAlias = $this->query->addExpression($expression, $this->field, [
      ':hosting_platform' => (int) $platform,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $alias = $this->fieldAlias ?: $this->field;
    return $values->{$alias} ?? 0;
  }

}
