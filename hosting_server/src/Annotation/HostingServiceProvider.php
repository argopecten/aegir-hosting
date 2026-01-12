<?php

namespace Drupal\hosting_server\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Hosting service provider plugin annotation.
 *
 * @Annotation
 */
class HostingServiceProvider extends Plugin {

  public string $id;
  public string $label;
  public string $service_type;

}
