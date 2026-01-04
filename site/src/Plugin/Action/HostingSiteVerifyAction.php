<?php

namespace Drupal\hosting_site\Plugin\Action;

/**
 * Verify a hosting site.
 *
 * @Action(
 *   id = "hosting_site_verify_action",
 *   label = @Translation("Verify site"),
 *   type = "hosting_site"
 * )
 */
class HostingSiteVerifyAction extends HostingSiteActionBase {

  /**
   * {@inheritdoc}
   */
  protected string $task = 'verify';

}
