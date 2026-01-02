<?php

namespace Drupal\hosting\Plugin\views\field;

use Drupal\views\ResultRow;

/**
 * Status field for hosting sites.
 *
 * @ViewsField("hosting_site_status")
 */
class HostingSiteStatusField extends HostingStatusFieldBase {

  /**
   * {@inheritdoc}
   */
  protected $additional_fields = ['verified'];

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    $output = _hosting_site_status($value);

    $verified = NULL;
    if (isset($this->aliases['verified']) && isset($values->{$this->aliases['verified']})) {
      $verified = $values->{$this->aliases['verified']};
    }
    elseif (isset($values->verified)) {
      $verified = $values->verified;
    }

    return $this->buildStatusOutput($output, _hosting_site_list_class($value, $verified));
  }

}
