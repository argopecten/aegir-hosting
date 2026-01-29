<?php

namespace Drupal\hosting_site\Entity;

use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * View builder handler for hosting site entities.
 */
class HostingSiteViewBuilder extends EntityViewBuilder implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public function view($entity, $view_mode = 'full', $langcode = NULL) {
    $build = parent::view($entity, $view_mode, $langcode);
    
    // Override the default theme to use our custom template.
    $build['#theme'] = 'hosting_site';
    $build['#hosting_site'] = $entity;
    $build['#view_mode'] = $view_mode;
    
    // Attach entity view library for styling.
    $build['#attached']['library'][] = 'hosting_site/hosting_site.entity_view';
    
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['build'];
  }

}
