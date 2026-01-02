<?php

namespace Drupal\hosting_platform\Plugin\Action;

/**
 * Delete a hosting platform.
 *
 * @Action(
 *   id = "hosting_platform_delete_action",
 *   label = @Translation("Delete platform"),
 *   type = "node"
 * )
 */
class HostingPlatformDeleteAction extends HostingPlatformActionBase {

  /**
   * {@inheritdoc}
   */
  protected string $task = 'delete';

  /**
   * {@inheritdoc}
   */
  protected string $requiredOperation = 'delete';

}
