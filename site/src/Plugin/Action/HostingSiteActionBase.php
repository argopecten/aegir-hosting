<?php

namespace Drupal\hosting_site\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Base action for site task operations.
 */
abstract class HostingSiteActionBase extends ActionBase {

  /**
   * The hosting task name to queue.
   *
   * @var string
   */
  protected string $task = '';

  /**
   * The entity operation required to run the action.
   *
   * @var string
   */
  protected string $requiredOperation = 'update';

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity instanceof EntityInterface || $entity->getEntityTypeId() !== 'hosting_site') {
      return;
    }

    \hosting_add_task($entity->id(), $this->task);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $account ?: \Drupal::currentUser();
    if (!$object instanceof EntityInterface || $object->getEntityTypeId() !== 'hosting_site') {
      $result = AccessResult::forbidden();
    }
    elseif (!$account->hasPermission('administer tasks') && !$account->hasPermission('create ' . $this->task . ' task')) {
      $result = AccessResult::forbidden()->cachePerPermissions();
    }
    else {
      $result = $object->access($this->requiredOperation, $account, TRUE);
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

}
