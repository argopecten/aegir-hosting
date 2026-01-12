<?php

namespace Drupal\hosting_server\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Hosting service type plugin annotation.
 *
 * @Annotation
 */
class HostingServiceType extends Plugin {

  public string $id;
  public string $label;

}
