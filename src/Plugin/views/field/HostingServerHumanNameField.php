<?php

namespace Drupal\hosting\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Human-readable name field for hosting servers.
 *
 * @ViewsField("hosting_server_human_name")
 */
class HostingServerHumanNameField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public $additional_fields = ['label'];

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    if ($value) {
      return $value;
    }

    if (isset($this->aliases['label']) && isset($values->{$this->aliases['label']})) {
      return $values->{$this->aliases['label']};
    }

    return $values->label ?? '';
  }

}
