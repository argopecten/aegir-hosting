<?php

namespace Drupal\hosting_platform\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to display platform release information.
 *
 * @ViewsField("hosting_platform_release")
 */
class HostingPlatformRelease extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\hosting_platform\Entity\HostingPlatform $entity */
    $entity = $this->getEntity($values);
    
    if (!$entity) {
      return '';
    }

    // Try to get release info from various fields
    if ($entity->hasField('release') && !$entity->get('release')->isEmpty()) {
      return $entity->get('release')->value;
    }
    
    if ($entity->hasField('php_version') && !$entity->get('php_version')->isEmpty()) {
      return $entity->get('php_version')->value;
    }
    
    if ($entity->hasField('drupal_version') && !$entity->get('drupal_version')->isEmpty()) {
      return $entity->get('drupal_version')->value;
    }

    return '';
  }

}
