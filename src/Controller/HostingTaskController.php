<?php

namespace Drupal\hosting\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HostingTaskController extends ControllerBase {
  /**
   * Load a hosting entity for task operations.
   */
  protected function loadTargetEntity($entity_type, $entity_id) {
    $entity_type_id = $entity_type;
    if (strpos($entity_type_id, 'hosting_') !== 0) {
      $entity_type_id = hosting_entity_type_from_task_scope($entity_type_id);
    }
    if (!$entity_type_id || !\Drupal::entityTypeManager()->hasDefinition($entity_type_id)) {
      return NULL;
    }
    return \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
  }

  /**
   * Render legacy task confirmation forms.
   */
  public function confirm($entity_type, $entity_id, $task_full) {
    $node = $this->loadTargetEntity($entity_type, $entity_id);
    if (!$node) {
      throw new NotFoundHttpException();
    }

    $task = $task_full;
    if (is_object($node)) {
      $scope = hosting_entity_task_scope($node);
      $prefix = $scope ? $scope . '_' : '';
      if (strpos($task_full, $prefix) === 0) {
        $task = substr($task_full, strlen($prefix));
      }
    }

    $tasks = hosting_available_tasks(hosting_entity_task_scope($node));
    if (!isset($tasks[$task]['dialog']) || !$tasks[$task]['dialog']) {
      hosting_add_task($node, $task);
      if ($task == 'delete') {
        \Drupal::messenger()->addMessage(t(':title has been queued for deletion.', array(':title' => hosting_entity_label($node))));
        return new RedirectResponse(Url::fromRoute('<front>')->toString());
      }
      if ($node instanceof \Drupal\Core\Entity\EntityInterface) {
        return new RedirectResponse($node->toUrl('canonical')->toString());
      }
      return new RedirectResponse(Url::fromUserInput('/hosting/' . hosting_entity_task_scope($node) . '/' . hosting_entity_id($node))->toString());
    }

    return $this->formBuilder()->getForm('hosting_task_confirm_form', $node, $task);
  }

  /**
   * Access check for task confirmation.
   */
  public function access(AccountInterface $account, $entity_type, $entity_id, $task_full) {
    $node = $this->loadTargetEntity($entity_type, $entity_id);
    if (!$node) {
      return AccessResult::forbidden();
    }
    $task = $task_full;
    if (is_object($node)) {
      $scope = hosting_entity_task_scope($node);
      $prefix = $scope ? $scope . '_' : '';
      if (strpos($task_full, $prefix) === 0) {
        $task = substr($task_full, strlen($prefix));
      }
    }
    return AccessResult::allowedIf(hosting_task_menu_access_csrf($node, $task));
  }

  /**
   * Access callback for task list refresh.
   */
  public function ajaxAccess(AccountInterface $account, $entity_type, $entity_id) {
    $node = $this->loadTargetEntity($entity_type, $entity_id);
    if (!$node) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowedIf($node->access('view', $account));
  }

  /**
   * Provide task list JSON for a hosting entity.
   */
  public function ajaxList($entity_type, $entity_id) {
    $node = $this->loadTargetEntity($entity_type, $entity_id);
    if (!$node) {
      throw new NotFoundHttpException();
    }
    return new JsonResponse(hosting_task_ajax_list($node));
  }

  /**
   * Provide task queue JSON.
   */
  public function ajaxQueue() {
    return new JsonResponse(hosting_task_ajax_queue());
  }

  /**
   * Cancel a queued task.
   */
  public function cancel($hosting_task) {
    return hosting_task_cancel($hosting_task);
  }

  /**
   * Access callback for task cancellation.
   */
  public function cancelAccess(AccountInterface $account, $hosting_task) {
    return AccessResult::allowedIf(hosting_task_cancel_access($hosting_task));
  }

  /**
   * Render a hosting task entity.
   */
  public function view($hosting_task) {
    return hosting_task_build_view($hosting_task);
  }

  /**
   * AJAX handler for task log updates.
   */
  public function logAjax($hosting_task, $last_position, $id) {
    return hosting_task_log_ajax($hosting_task, $last_position, $id);
  }

}
