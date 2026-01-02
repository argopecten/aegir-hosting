<?php

namespace Drupal\hosting_site\Plugin\Action;

/**
 * Enable a hosting site.
 *
 * @Action(
 *   id = "hosting_site_enable_action",
 *   label = @Translation("Enable site"),
 *   type = "node"
 * )
 */
class HostingSiteEnableAction extends HostingSiteActionBase {

  /**
   * {@inheritdoc}
   */
  protected string $task = 'enable';

}
