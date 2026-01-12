<?php

namespace Drupal\hosting_server\Service;

use Drupal\hosting_server\Plugin\HostingServiceProviderManager;
use Drupal\hosting_server\Plugin\HostingServiceTypeManager;

class ServiceManager {

  protected HostingServiceTypeManager $typeManager;
  protected HostingServiceProviderManager $providerManager;

  public function __construct(HostingServiceTypeManager $typeManager, HostingServiceProviderManager $providerManager) {
    $this->typeManager = $typeManager;
    $this->providerManager = $providerManager;
  }

  public function getServiceTypes(): array {
    $definitions = $this->typeManager->getDefinitions();
    $options = [];
    foreach ($definitions as $id => $definition) {
      $options[$id] = $definition['label'] ?? $id;
    }
    return $options;
  }

  public function getProvidersForType(string $service_type): array {
    $definitions = $this->providerManager->getDefinitions();
    $options = [];
    foreach ($definitions as $id => $definition) {
      if (($definition['service_type'] ?? '') === $service_type) {
        $options[$id] = $definition['label'] ?? $id;
      }
    }
    return $options;
  }

  public function createProvider(string $provider_id, array $configuration = []) {
    return $this->providerManager->createInstance($provider_id, $configuration);
  }

}
