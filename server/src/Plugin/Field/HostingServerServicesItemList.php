<?php

namespace Drupal\hosting_server\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed field for server service entries.
 */
class HostingServerServicesItemList extends FieldItemList {

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

    $result = \Drupal::database()->select('hosting_service', 'hs')
      ->fields('hs', ['service', 'type'])
      ->condition('server_id', $server_id)
      ->orderBy('service')
      ->execute();

    $delta = 0;
    foreach ($result as $record) {
      $value = $record->service . ':' . $record->type;
      $this->list[$delta] = $this->createItem($delta, $value);
      $delta++;
    }
  }

}
