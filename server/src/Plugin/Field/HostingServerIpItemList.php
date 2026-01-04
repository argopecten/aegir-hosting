<?php

namespace Drupal\hosting_server\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed field for server IP addresses.
 */
class HostingServerIpItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    if (!$entity || !method_exists($entity, 'id')) {
      return;
    }

    $server_id = (int) $entity->id();
    if (!$server_id) {
      return;
    }

    $result = \Drupal::database()->select('hosting_ip_addresses', 'ip')
      ->fields('ip', ['ip_address'])
      ->condition('server_id', $server_id)
      ->orderBy('id')
      ->execute();

    $delta = 0;
    foreach ($result as $record) {
      $this->list[$delta] = $this->createItem($delta, $record->ip_address);
      $delta++;
    }
  }

}
