<?php

namespace Drupal\hosting\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Language field for hosting sites.
 *
 * @ViewsField("hosting_site_language")
 */
class HostingSiteLanguageField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    return _hosting_language_name($value);
  }

}
