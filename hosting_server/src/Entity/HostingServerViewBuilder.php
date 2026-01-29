<?php

namespace Drupal\hosting_server\Entity;

use Drupal\Core\Entity\EntityViewBuilder;

/**
 * View builder handler for hosting server entities.
 */
class HostingServerViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function view($entity, $view_mode = 'full', $langcode = NULL) {
    $build = parent::view($entity, $view_mode, $langcode);
    
    // Override the default theme to use our custom template.
    $build['#theme'] = 'hosting_server';
    $build['#hosting_server'] = $entity;
    $build['#view_mode'] = $view_mode;
    
    // Attach entity view library for styling.
    $build['#attached']['library'][] = 'hosting_server/hosting_server.entity_view';
    
    return $build;
  }

}
