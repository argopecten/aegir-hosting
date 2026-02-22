<?php

namespace Drupal\hosting_task\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\hosting_task\Service\TaskAvailabilityResolver;
use Drupal\hosting_task\Service\TaskLogBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * AJAX controller for refreshing task table and task log fragments.
 */
class TaskRefreshController extends ControllerBase {

  protected TaskAvailabilityResolver $taskResolver;
  protected TaskLogBuilder $taskLogBuilder;
  protected RendererInterface $renderer;

  public function __construct(
    TaskAvailabilityResolver $taskResolver,
    TaskLogBuilder $taskLogBuilder,
    RendererInterface $renderer,
  ) {
    $this->taskResolver = $taskResolver;
    $this->taskLogBuilder = $taskLogBuilder;
    $this->renderer = $renderer;
  }

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('hosting.task_availability_resolver'),
      $container->get('hosting.task_log_builder'),
      $container->get('renderer'),
    );
  }

  /**
   * Return the task table HTML fragment for an entity.
   *
   * @param string $entity_type_id
   *   The entity type ID (hosting_site, hosting_platform, hosting_server).
   * @param int $entity_id
   *   The entity ID.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   HTML fragment response.
   */
  public function refreshTaskTable(string $entity_type_id, int $entity_id): Response {
    $allowed = ['hosting_site', 'hosting_platform', 'hosting_server'];
    if (!in_array($entity_type_id, $allowed, TRUE)) {
      throw new NotFoundHttpException();
    }

    $entity = $this->entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
    if (!$entity) {
      throw new NotFoundHttpException();
    }

    $build = $this->taskResolver->buildTaskTable($entity);
    $html = (string) $this->renderer->renderInIsolation($build);

    return new Response($html, 200, [
      'Content-Type' => 'text/html; charset=UTF-8',
      'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
  }

  /**
   * Return the task log HTML fragment for a task.
   *
   * @param int $hosting_task
   *   The task entity ID.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   HTML fragment response.
   */
  public function refreshTaskLog(int $hosting_task): Response {
    $task = $this->entityTypeManager()->getStorage('hosting_task')->load($hosting_task);
    if (!$task) {
      throw new NotFoundHttpException();
    }

    $build = $this->taskLogBuilder->build($task);
    $html = (string) $this->renderer->renderInIsolation($build);

    return new Response($html, 200, [
      'Content-Type' => 'text/html; charset=UTF-8',
      'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
  }

}
