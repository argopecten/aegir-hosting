<?php

namespace Drupal\hosting_db_server\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hosting_server\Entity\HostingServer;

class DbServerResolver {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public function resolve(string $identifier): ?HostingServer {
    if (filter_var($identifier, FILTER_VALIDATE_IP) !== FALSE) {
      return $this->findByIp($identifier);
    }

    return $this->findByHostname($identifier);
  }

  public function findByHostname(string $hostname): ?HostingServer {
    $storage = $this->entityTypeManager->getStorage('hosting_server');
    $results = $storage->loadByProperties(['hostname' => $hostname]);
    return $results ? reset($results) : NULL;
  }

  public function findByIp(string $ip): ?HostingServer {
    $storage = $this->entityTypeManager->getStorage('hosting_server');
    $results = $storage->loadByProperties(['ip_addresses' => $ip]);
    return $results ? reset($results) : NULL;
  }

}
