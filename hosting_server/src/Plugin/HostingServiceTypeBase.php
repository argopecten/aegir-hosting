<?php

namespace Drupal\hosting_server\Plugin;

use Drupal\Component\Plugin\PluginBase;

abstract class HostingServiceTypeBase extends PluginBase implements HostingServiceTypeInterface {

  public function getLabel(): string {
    return (string) ($this->pluginDefinition['label'] ?? $this->getPluginId());
  }

}
