<?php

namespace Drupal\hosting_site\Plugin\Action;

/**
 * Enable a hosting site.
 *
 * @Action(
 *   id = "hosting_site_enable_action",
 *   label = @Translation("Enable site"),
 *   type = "hosting_site"
 * )
 */
class HostingSiteEnableAction extends HostingSiteActionBase {

  /**
   * {@inheritdoc}
   */
  protected string $task = 'enable';

}
