<?php

namespace Drupal\hosting_example\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Attributes as Drush;
use Drush\Commands\DrushCommands;
use Drush\HookManager\HookManager;

final class HostingExampleCommands extends DrushCommands {
  #[Drush\Hook(type: HookManager::INIT)]
  public function init(CommandData $commandData): void {
    example_provision_register_autoload();
  }
}
