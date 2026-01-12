<?php

namespace Drupal\hosting_client\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

class ClientManager {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public function sanitizeUname(string $value): string {
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
    $value = trim($value, '_');
    return $value !== '' ? $value : 'client';
  }

  public function isUnameUnique(string $uname, ?int $exclude_id = NULL): bool {
    $query = $this->entityTypeManager->getStorage('hosting_client')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uname', $uname);
    if ($exclude_id) {
      $query->condition('id', $exclude_id, '<>');
    }
    return $query->count()->execute() == 0;
  }

  public function generateUniqueUname(string $name, ?int $exclude_id = NULL): string {
    $base = $this->sanitizeUname($name);
    $candidate = $base;
    $suffix = 1;

    while (!$this->isUnameUnique($candidate, $exclude_id)) {
      $candidate = $base . '_' . $suffix;
      $suffix++;
    }

    return $candidate;
  }

}
