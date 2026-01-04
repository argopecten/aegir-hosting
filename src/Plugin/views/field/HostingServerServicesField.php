<?php

namespace Drupal\hosting\Plugin\views\field;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Services field for hosting servers.
 *
 * @ViewsField("hosting_server_services")
 */
class HostingServerServicesField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function label() {
    return parent::label();
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $server_id = $this->getValue($values);
    if (!$server_id) {
      return '';
    }

    $all_services = hosting_server_services();
    $types = [];
    foreach ($all_services as $type => $service) {
      $types[$type] = 'no';
    }

    $records = \Drupal::database()->query(
      'SELECT service, type FROM {hosting_service} WHERE server_id = :server_id',
      [':server_id' => $server_id]
    );
    foreach ($records as $record) {
      $types[$record->service] = $record->type ?: 'yes';
    }

    return implode(',', $types);
  }

}
