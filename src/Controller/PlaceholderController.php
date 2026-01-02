<?php

namespace Drupal\hosting\Controller;

use Drupal\Core\Controller\ControllerBase;

class PlaceholderController extends ControllerBase {
  /**
   * Render a placeholder response for unported listings.
   */
  public function listing($message = NULL) {
    $message = $message ?: 'This listing has not been ported to Drupal 11 yet.';
    return array('#markup' => $message);
  }

}
