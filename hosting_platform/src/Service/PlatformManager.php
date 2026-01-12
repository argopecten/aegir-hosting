<?php

namespace Drupal\hosting_platform\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hosting_platform\Entity\HostingPlatform;

class PlatformManager {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected ConfigFactoryInterface $configFactory;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  public function isValidPublishPath(string $path): bool {
    $path = trim($path);
    if ($path === '') {
      return FALSE;
    }
    if (!str_starts_with($path, '/')) {
      return FALSE;
    }
    return TRUE;
  }

  public function isPublishPathUnique(string $path, ?int $exclude_id = NULL): bool {
    $query = $this->entityTypeManager
      ->getStorage('hosting_platform')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('publish_path', $path);

    if ($exclude_id) {
      $query->condition('id', $exclude_id, '<>');
    }

    return $query->count()->execute() == 0;
  }

  public function getDefaultStatus(): int {
    $config = $this->configFactory->get('hosting.settings');
    if ($config->get('settings.lock_platforms_by_default')) {
      return HostingPlatform::STATUS_LOCKED;
    }
    return HostingPlatform::STATUS_QUEUED;
  }

}
