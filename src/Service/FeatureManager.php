<?php

namespace Drupal\hosting\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class FeatureManager {

  use StringTranslationTrait;

  protected ModuleHandlerInterface $moduleHandler;
  protected ConfigFactoryInterface $configFactory;
  protected ModuleInstallerInterface $moduleInstaller;
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(ModuleHandlerInterface $moduleHandler, ConfigFactoryInterface $configFactory, ModuleInstallerInterface $moduleInstaller, EntityTypeManagerInterface $entityTypeManager) {
    $this->moduleHandler = $moduleHandler;
    $this->configFactory = $configFactory;
    $this->moduleInstaller = $moduleInstaller;
    $this->entityTypeManager = $entityTypeManager;
  }

  public function getFeatures(): array {
    $features = $this->moduleHandler->invokeAll('hosting_feature');

    $features['core'] = [
      'title' => $this->t('Core Hosting'),
      'description' => $this->t('Required Hosting core functionality.'),
      'required' => TRUE,
      'group' => 'required',
    ];

    foreach ($features as $key => $info) {
      $features[$key] = $info + [
        'title' => $key,
        'description' => '',
        'group' => 'optional',
        'required' => FALSE,
        'dependencies' => [],
        'role_permissions' => [],
      ];
    }

    return $features;
  }

  public function getEnabled(): array {
    return $this->configFactory->get('hosting.features')->get('enabled') ?? [];
  }

  public function applyEnabledFeatures(array $enabled): void {
    $features = $this->getFeatures();
    $enabled = $this->normalizeEnabled($enabled, $features);
    $current = $this->getEnabled();

    $to_enable = array_diff($enabled, $current);
    $to_disable = array_diff($current, $enabled);

    $modules_to_enable = $this->collectModulesToEnable($to_enable, $features);
    if ($modules_to_enable) {
      $this->moduleInstaller->install($modules_to_enable, TRUE);
    }

    $modules_to_disable = $this->collectModulesToDisable($to_disable, $features);
    if ($modules_to_disable) {
      $this->moduleInstaller->uninstall($modules_to_disable, TRUE);
    }

    $this->applyRolePermissions($enabled, $features);

    $this->configFactory->getEditable('hosting.features')
      ->set('enabled', $enabled)
      ->save();
  }

  protected function normalizeEnabled(array $enabled, array $features): array {
    $enabled = array_values(array_unique($enabled));
    foreach ($features as $key => $info) {
      if (!empty($info['required']) && !in_array($key, $enabled, TRUE)) {
        $enabled[] = $key;
      }
    }
    return $enabled;
  }

  protected function collectModulesToEnable(array $feature_keys, array $features): array {
    $modules = [];
    foreach ($feature_keys as $feature_key) {
      if (!isset($features[$feature_key]['module'])) {
        continue;
      }
      $modules[] = $features[$feature_key]['module'];
      foreach ($features[$feature_key]['dependencies'] as $dependency) {
        $modules[] = $dependency;
      }
    }
    return array_values(array_unique($modules));
  }

  protected function collectModulesToDisable(array $feature_keys, array $features): array {
    $modules = [];
    foreach ($feature_keys as $feature_key) {
      if (!empty($features[$feature_key]['required'])) {
        continue;
      }
      if (!isset($features[$feature_key]['module'])) {
        continue;
      }
      $modules[] = $features[$feature_key]['module'];
    }
    return array_values(array_unique($modules));
  }

  protected function applyRolePermissions(array $enabled, array $features): void {
    $role_storage = $this->entityTypeManager->getStorage('user_role');
    $roles = $role_storage->loadMultiple();
    $roles_by_label = [];
    foreach ($roles as $role) {
      $roles_by_label[$role->label()] = $role;
    }

    foreach ($enabled as $feature_key) {
      $role_permissions = $features[$feature_key]['role_permissions'] ?? [];
      foreach ($role_permissions as $role_label => $permissions) {
        if (!isset($roles_by_label[$role_label])) {
          continue;
        }
        $role = $roles_by_label[$role_label];
        foreach ($permissions as $permission) {
          $role->grantPermission($permission);
        }
        $role->save();
      }
    }
  }

}
