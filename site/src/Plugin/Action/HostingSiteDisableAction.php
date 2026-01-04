<?php

namespace Drupal\hosting_site\Plugin\Action;

/**
 * Disable a hosting site.
 *
 * @Action(
 *   id = "hosting_site_disable_action",
 *   label = @Translation("Disable site"),
 *   type = "hosting_site"
 * )
 */
class HostingSiteDisableAction extends HostingSiteActionBase {

  /**
   * {@inheritdoc}
   */
  protected string $task = 'disable';

}
