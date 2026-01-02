<?php

namespace Drupal\hosting_site\Plugin\Action;

/**
 * Backup a hosting site.
 *
 * @Action(
 *   id = "hosting_site_backup_action",
 *   label = @Translation("Backup site"),
 *   type = "node"
 * )
 */
class HostingSiteBackupAction extends HostingSiteActionBase {

  /**
   * {@inheritdoc}
   */
  protected string $task = 'backup';

}
