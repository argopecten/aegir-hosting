<?php

namespace Drupal\hosting_server\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;

class HostingServiceProviderManager extends DefaultPluginManager {

  public function __construct($namespaces, $cache_backend, $module_handler) {
    parent::__construct('Plugin/HostingServiceProvider', $namespaces, $module_handler, HostingServiceProviderInterface::class, 'Drupal\\hosting_server\\Annotation\\HostingServiceProvider');
    $this->alterInfo('hosting_service_provider_info');
    $this->setCacheBackend($cache_backend, 'hosting_service_provider_plugins');
  }

}
