<?php

namespace Drupal\hosting_server\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

interface HostingServiceTypeInterface extends PluginInspectionInterface {

  public function getLabel(): string;

}
