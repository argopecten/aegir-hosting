<?php

namespace Drupal\hosting_platform\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to display platform status as a CSS class or badge.
 *
 * @ViewsField("hosting_platform_status")
 */
class HostingPlatformStatus extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['status_mode'] = ['default' => 'class'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\hosting_platform\Entity\HostingPlatform $entity */
    $entity = $this->getEntity($values);
    
    if (!$entity) {
      return '';
    }

    $status = $entity->hasField('status') && !$entity->get('status')->isEmpty() 
      ? $entity->get('status')->value 
      : 0;

    // Map status codes to labels
    $status_labels = [
      '-2' => 'deleted',
      '-1' => 'disabled',
      '0' => 'new',
      '1' => 'enabled',
      '2' => 'locked',
    ];

    $label = $status_labels[$status] ?? 'unknown';

    if ($this->options['status_mode'] === 'class') {
      // Return just the class name for use in row_class
      return 'hosting-status-' . $label;
    }

    // Return a badge
    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => ucfirst($label),
      '#attributes' => [
        'class' => ['badge', 'hosting-status-' . $label],
      ],
    ];
  }

}
