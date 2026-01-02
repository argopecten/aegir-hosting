<?php

namespace Drupal\hosting\Plugin\views\field;

use Drupal\views\ResultRow;

/**
 * Status field for hosting servers.
 *
 * @ViewsField("hosting_server_status")
 */
class HostingServerStatusField extends HostingStatusFieldBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    $output = _hosting_server_status($value);

    return $this->buildStatusOutput($output, _hosting_server_list_class($value));
  }

}
