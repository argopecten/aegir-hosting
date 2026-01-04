<?php

namespace Drupal\hosting_site\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Delete a hosting site.
 *
 * @Action(
 *   id = "hosting_site_delete_action",
 *   label = @Translation("Delete site"),
 *   type = "hosting_site"
 * )
 */
class HostingSiteDeleteAction extends HostingSiteActionBase {

  /**
   * {@inheritdoc}
   */
  protected string $task = 'delete';

  /**
   * {@inheritdoc}
   */
  protected string $requiredOperation = 'delete';

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = parent::access($object, $account, TRUE);
    if ($result->isAllowed()) {
      $config = \Drupal::config('hosting.settings');
      $require_disable = $config->get('hosting_require_disable_before_delete') ?? TRUE;
      $result = $result->addCacheableDependency($config);
      if ($require_disable && defined('HOSTING_SITE_ENABLED') && isset($object->site_status) && $object->site_status == HOSTING_SITE_ENABLED) {
        $result = AccessResult::forbidden()->addCacheableDependency($config);
      }
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

}
