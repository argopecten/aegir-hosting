<?php

namespace Drupal\hosting_platform\Entity;

use Drupal\Core\Entity\EntityViewBuilder;

/**
 * View builder handler for hosting platform entities.
 */
class HostingPlatformViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function view($entity, $view_mode = 'full', $langcode = NULL) {
    $build = parent::view($entity, $view_mode, $langcode);
    
    // Override the default theme to use our custom template.
    $build['#theme'] = 'hosting_platform';
    $build['#hosting_platform'] = $entity;
    $build['#view_mode'] = $view_mode;
    
    // Attach entity view library for styling.
    $build['#attached']['library'][] = 'hosting_platform/hosting_platform.entity_view';
    
    return $build;
  }

}
