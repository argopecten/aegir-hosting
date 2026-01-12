<?php

namespace Drupal\hosting_site\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

class BackupManager {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public function createBackup(int $site_id, array $values = []) {
    $storage = $this->entityTypeManager->getStorage('hosting_site_backup');
    $values += [
      'site' => $site_id,
      'timestamp' => time(),
    ];

    $backup = $storage->create($values);
    $backup->save();
    return $backup;
  }

  public function deleteBackup(int $backup_id): void {
    $storage = $this->entityTypeManager->getStorage('hosting_site_backup');
    $backup = $storage->load($backup_id);
    if ($backup) {
      $backup->delete();
    }
  }

  public function getBackupsForSite(int $site_id): array {
    $storage = $this->entityTypeManager->getStorage('hosting_site_backup');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('site', $site_id)
      ->sort('timestamp', 'DESC');
    $ids = $query->execute();
    if (!$ids) {
      return [];
    }
    return $storage->loadMultiple($ids);
  }

}
