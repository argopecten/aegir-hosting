<?php

namespace Drupal\hosting_site_data\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Attributes as Drush;
use Drush\Commands\DrushCommands;
use Drush\HookManager\HookManager;

final class HostingSiteDataCommands extends DrushCommands {
  #[Drush\Hook(type: HookManager::INIT)]
  public function init(CommandData $commandData): void {
    site_data_provision_register_autoload();
  }

  #[Drush\Hook(type: HookManager::POST_COMMAND, target: 'provision-install')]
  public function postProvisionInstall(CommandData $commandData): void {
    if (!function_exists('d')) {
      return;
    }
    $this->logger()->notice('site_data received from the frontend (d()->site_data): ' . d()->site_data);
  }

  #[Drush\Hook(type: HookManager::POST_COMMAND, target: 'provision-verify')]
  public function postProvisionVerify(CommandData $commandData): void {
    if (!function_exists('d')) {
      return;
    }
    $this->logger()->notice('site_data received from the frontend (d()->site_data): ' . d()->site_data);
  }
}
