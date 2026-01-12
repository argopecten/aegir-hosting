<?php

namespace Drupal\hosting_package\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

class PackageComparison {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public function comparePlatforms(int $platform_a, int $platform_b): array {
    $instances_a = $this->loadPlatformInstances($platform_a);
    $instances_b = $this->loadPlatformInstances($platform_b);

    $missing_in_b = array_diff_key($instances_a, $instances_b);
    $missing_in_a = array_diff_key($instances_b, $instances_a);
    $different = [];

    foreach ($instances_a as $name => $version_code) {
      if (!isset($instances_b[$name])) {
        continue;
      }
      if ($version_code !== $instances_b[$name]) {
        $different[$name] = [
          'a' => $version_code,
          'b' => $instances_b[$name],
        ];
      }
    }

    return [
      'missing_in_a' => array_keys($missing_in_a),
      'missing_in_b' => array_keys($missing_in_b),
      'different' => $different,
    ];
  }

  protected function loadPlatformInstances(int $platform_id): array {
    $storage = $this->entityTypeManager->getStorage('hosting_package_instance');
    $instances = $storage->loadByProperties(['platform' => $platform_id]);

    $result = [];
    foreach ($instances as $instance) {
      $package = $instance->get('package')->entity;
      if (!$package) {
        continue;
      }
      $short_name = (string) $package->get('short_name')->value;
      if ($short_name === '') {
        continue;
      }
      $result[$short_name] = (int) $instance->get('version_code')->value;
    }

    return $result;
  }

}
