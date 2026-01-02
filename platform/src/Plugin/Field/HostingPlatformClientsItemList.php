<?php

namespace Drupal\hosting_platform\Plugin\Field;

use Drupal\Core\Field\ComputedFieldItemList;

/**
 * Computed field for platform client access.
 */
class HostingPlatformClientsItemList extends ComputedFieldItemList {

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
