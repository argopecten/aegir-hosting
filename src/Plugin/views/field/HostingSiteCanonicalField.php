<?php

namespace Drupal\hosting\Plugin\views\field;

use Drupal\hosting_site\Entity\HostingSite;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Canonical URL field for hosting sites.
 *
 * @ViewsField("hosting_site_canonical")
 */
class HostingSiteCanonicalField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $site_id = $this->getValue($values);
    if (!$site_id) {
      return '';
    }

    $site = $values->_entity instanceof HostingSite
      ? $values->_entity
      : \Drupal::entityTypeManager()->getStorage('hosting_site')->load($site_id);

    if ($site) {
      return hosting_site_canonical_url($site);
    }

    if (isset($values->node_hosting_task_title)) {
      return $values->node_hosting_task_title;
    }
    if (isset($values->node_title)) {
      return $values->node_title;
    }
    if ($node) {
      return $node->label();
    }

    return '';
  }

}
