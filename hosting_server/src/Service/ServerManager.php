<?php

namespace Drupal\hosting_server\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

class ServerManager {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public function isValidHostname(string $hostname): bool {
    if (filter_var($hostname, FILTER_VALIDATE_IP) !== FALSE) {
      return TRUE;
    }
    if (filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== FALSE) {
      return TRUE;
    }
    return FALSE;
  }

  public function isHostnameUnique(string $hostname, ?int $exclude_id = NULL): bool {
    $query = $this->entityTypeManager
      ->getStorage('hosting_server')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('hostname', $hostname);

    if ($exclude_id) {
      $query->condition('id', $exclude_id, '<>');
    }

    return $query->count()->execute() == 0;
  }

}
