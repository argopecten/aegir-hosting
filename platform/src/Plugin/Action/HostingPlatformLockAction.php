<?php

namespace Drupal\hosting_platform\Plugin\Action;

/**
 * Lock a hosting platform.
 *
 * @Action(
 *   id = "hosting_platform_lock_action",
 *   label = @Translation("Lock platform"),
 *   type = "hosting_platform"
 * )
 */
class HostingPlatformLockAction extends HostingPlatformActionBase {

  /**
   * {@inheritdoc}
   */
  protected string $task = 'lock';

}
