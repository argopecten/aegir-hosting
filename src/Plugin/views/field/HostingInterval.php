<?php

namespace Drupal\hosting\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Formats timestamps into human readable intervals.
 *
 * @ViewsField("hosting_interval")
 */
class HostingInterval extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    if ($value === NULL || $value === '') {
      return '';
    }

    return hosting_format_interval($value);
  }

}
