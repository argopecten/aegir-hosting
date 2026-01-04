<?php

namespace Drupal\hosting_platform\Plugin\Action;

/**
 * Unlock a hosting platform.
 *
 * @Action(
 *   id = "hosting_platform_unlock_action",
 *   label = @Translation("Unlock platform"),
 *   type = "hosting_platform"
 * )
 */
class HostingPlatformUnlockAction extends HostingPlatformActionBase {

  /**
   * {@inheritdoc}
   */
  protected string $task = 'unlock';

}
