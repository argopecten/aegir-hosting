<?php

namespace Drupal\hosting_server\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

interface HostingServiceProviderInterface extends PluginInspectionInterface, PluginFormInterface, ConfigurableInterface {

  public function getLabel(): string;

  public function getServiceType(): string;

  public function defaultConfiguration(): array;

}
