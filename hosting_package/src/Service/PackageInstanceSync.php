<?php

namespace Drupal\hosting_package\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

class PackageInstanceSync {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected PackageDiscovery $discovery;
  protected PackageVersionParser $versionParser;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, PackageDiscovery $discovery, PackageVersionParser $version_parser) {
    $this->entityTypeManager = $entity_type_manager;
    $this->discovery = $discovery;
    $this->versionParser = $version_parser;
  }

  public function syncPlatform(int $platform_id, string $root): array {
    $package_storage = $this->entityTypeManager->getStorage('hosting_package');
    $instance_storage = $this->entityTypeManager->getStorage('hosting_package_instance');

    $existing_instances = $instance_storage->loadByProperties(['platform' => $platform_id]);
    $instance_map = [];
    foreach ($existing_instances as $instance) {
      $package_id = (int) $instance->get('package')->target_id;
      if ($package_id) {
        $instance_map[$package_id] = $instance;
      }
    }

    $discovered = $this->discovery->discover($root);
    $seen_package_ids = [];

    foreach ($discovered as $info) {
      $package = $this->loadOrCreatePackage($info, $package_storage);
      $seen_package_ids[] = $package->id();

      $instance = $instance_map[$package->id()] ?? $instance_storage->create();
      $instance->set('platform', $platform_id);
      $instance->set('package', $package->id());
      $instance->set('filename', (string) ($info['path'] ?? ''));
      $instance->set('version', (string) ($info['version'] ?? ''));
      $instance->set('version_code', $this->versionParser->toCode((string) ($info['version'] ?? '')));
      $instance->set('status', 1);

      if ($instance->hasField('platform_ref')) {
        $instance->set('platform_ref', (string) $platform_id);
      }

      $instance->save();
    }

    foreach ($instance_map as $package_id => $instance) {
      if (!in_array($package_id, $seen_package_ids, TRUE)) {
        $instance->set('status', 0);
        $instance->save();
      }
    }

    return $discovered;
  }

  protected function loadOrCreatePackage(array $info, $storage) {
    $short_name = (string) ($info['short_name'] ?? '');
    $type = (string) ($info['package_type'] ?? '');

    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('short_name', $short_name)
      ->condition('package_type', $type)
      ->range(0, 1);
    $ids = $query->execute();
    if ($ids) {
      $id = reset($ids);
      return $storage->load($id);
    }

    $package = $storage->create([
      'package_type' => $type,
      'short_name' => $short_name,
      'old_short_name' => (string) ($info['old_short_name'] ?? ''),
      'description' => (string) ($info['description'] ?? ''),
    ]);
    $package->save();

    return $package;
  }

}
