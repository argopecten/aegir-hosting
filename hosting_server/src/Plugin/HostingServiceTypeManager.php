<?php

namespace Drupal\hosting_server\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;

class HostingServiceTypeManager extends DefaultPluginManager {

  public function __construct($namespaces, $cache_backend, $module_handler) {
    parent::__construct('Plugin/HostingServiceType', $namespaces, $module_handler, HostingServiceTypeInterface::class, 'Drupal\\hosting_server\\Annotation\\HostingServiceType');
    $this->alterInfo('hosting_service_type_info');
    $this->setCacheBackend($cache_backend, 'hosting_service_type_plugins');
  }

}
