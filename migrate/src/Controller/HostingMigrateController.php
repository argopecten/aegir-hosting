<?php

namespace Drupal\hosting_migrate\Controller;

use Drupal\Core\Controller\ControllerBase;

class HostingMigrateController extends ControllerBase {
  /**
   * Render the package comparison for migrations.
   */
  public function comparison($current, $target) {
    return hosting_migrate_comparison($current, $target);
  }

}
