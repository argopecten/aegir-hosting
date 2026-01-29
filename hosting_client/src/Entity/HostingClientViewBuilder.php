<?php

namespace Drupal\hosting_client\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * View builder for hosting_client entities.
 */
class HostingClientViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL): array {
    $build = [
      '#theme' => 'hosting_client',
      '#hosting_client' => $entity,
      '#view_mode' => $view_mode,
    ];

    // Attach the hosting_client library for styling.
    $build['#attached']['library'][] = 'hosting_client/hosting-client';

    return $build;
  }

}
