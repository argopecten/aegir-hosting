<?php

namespace Drupal\hosting_platform\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Provides selection handler for web servers only.
 *
 * @EntityReferenceSelection(
 *   id = "default:hosting_server:web",
 *   label = @Translation("Web Server Selection"),
 *   entity_types = {"hosting_server"},
 *   group = "default",
 *   weight = 1
 * )
 */
class WebServerSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    // Get server IDs that have the 'http' service
    $server_ids = \Drupal::database()->query(
      'SELECT DISTINCT server_id FROM {hosting_service} WHERE available = :available AND service = :service',
      [':available' => 1, ':service' => 'http']
    )->fetchCol();

    if (empty($server_ids)) {
      return [];
    }

    // Get referenceable entities from parent, then filter to only web servers
    $options = parent::getReferenceableEntities($match, $match_operator, $limit);
    
    // Filter to only include servers with http service
    foreach ($options as $bundle => &$entities) {
      $entities = array_intersect_key($entities, array_flip($server_ids));
    }
    
    return array_filter($options);
  }

}
