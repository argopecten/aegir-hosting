<?php

namespace Drupal\hosting_client\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;

class ClientAccessManager {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccountProxyInterface $currentUser;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  public function getClientIdsForUser(?AccountInterface $account = NULL): array {
    $account = $account ?: $this->currentUser;
    $query = $this->entityTypeManager
      ->getStorage('hosting_client_user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('user', $account->id());
    $ids = $query->execute();

    if (!$ids) {
      return [];
    }

    $relations = $this->entityTypeManager->getStorage('hosting_client_user')->loadMultiple($ids);
    $client_ids = [];
    foreach ($relations as $relation) {
      $client_id = $relation->get('client')->target_id;
      if ($client_id) {
        $client_ids[] = (int) $client_id;
      }
    }

    return array_values(array_unique($client_ids));
  }

  public function userHasClient(AccountInterface $account, int $client_id): bool {
    if ($account->hasPermission('administer clients')) {
      return TRUE;
    }

    $client_ids = $this->getClientIdsForUser($account);
    return in_array($client_id, $client_ids, TRUE);
  }

  public function applyClientCondition($query, AccountInterface $account, string $field_name = 'client'): void {
    if ($account->hasPermission('administer clients')) {
      return;
    }

    $client_ids = $this->getClientIdsForUser($account);
    if (!$client_ids) {
      $query->condition($field_name, 0);
      return;
    }

    $query->condition($field_name, $client_ids, 'IN');
  }

}
