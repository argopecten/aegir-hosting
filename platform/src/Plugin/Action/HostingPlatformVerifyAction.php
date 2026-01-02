<?php

namespace Drupal\hosting_platform\Plugin\Action;

/**
 * Verify a hosting platform.
 *
 * @Action(
 *   id = "hosting_platform_verify_action",
 *   label = @Translation("Verify platform"),
 *   type = "node"
 * )
 */
class HostingPlatformVerifyAction extends HostingPlatformActionBase {

  /**
   * {@inheritdoc}
   */
  protected string $task = 'verify';

}
