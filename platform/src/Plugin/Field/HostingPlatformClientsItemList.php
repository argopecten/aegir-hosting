<?php

namespace Drupal\hosting_platform\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed field for platform client access.
 */
class HostingPlatformClientsItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    if (!$entity || !method_exists($entity, 'id')) {
      return;
    }

    $platform_id = (int) $entity->id();
    if (!$platform_id) {
      return;
    }

    $result = \Drupal::database()->select('hosting_platform_client_access', 'hpca')
      ->fields('hpca', ['cid'])
      ->condition('pid', $platform_id)
      ->orderBy('cid')
      ->execute();

    $delta = 0;
    foreach ($result as $record) {
      $this->list[$delta] = $this->createItem($delta, ['target_id' => (int) $record->cid]);
      $delta++;
    }
  }

}
