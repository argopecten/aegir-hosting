<?php

namespace Drupal\hosting\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;

/**
 * Builds the hosting sidebar for listing / admin pages.
 *
 * Provides a consistent sidebar matching D7 Aegir layout with:
 * - Task Queue: 5 most recent tasks.
 * - Navigation: Quick links relevant to the current context.
 * - Queue Summary: Status of hosting queues.
 */
class HostingSidebarBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected QueueDispatcher $queueDispatcher,
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Builds the sidebar render array for hosting listing pages.
   *
   * @return array
   *   A render array using the 'hosting_sidebar' theme hook.
   */
  public function build(): array {
    return [
      '#theme' => 'hosting_sidebar',
      '#content' => [
        'task_queue' => $this->buildTaskQueue(),
        'navigation' => $this->buildNavigation(),
        'queue_summary' => $this->buildQueueSummary(),
      ],
      '#attributes' => ['class' => ['hosting-sidebar']],
      '#cache' => [
        'tags' => ['hosting_task_list'],
        'contexts' => ['route'],
        'max-age' => 60,
      ],
    ];
  }

  /**
   * Builds the recent task queue section.
   */
  protected function buildTaskQueue(): array {
    $task_items = [];
    $task_tags = [];

    try {
      $task_ids = $this->entityTypeManager
        ->getStorage('hosting_task')
        ->getQuery()
        ->accessCheck(TRUE)
        ->sort('id', 'DESC')
        ->range(0, 5)
        ->execute();

      if ($task_ids) {
        $tasks = $this->entityTypeManager
          ->getStorage('hosting_task')
          ->loadMultiple($task_ids);

        foreach ($tasks as $task) {
          $status = (string) $task->get('status')->value;
          $css_class = 'hosting-task-item--' . Html::getClass($status);

          $task_items[] = [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['hosting-task-item', $css_class],
            ],
            'title' => [
              '#type' => 'link',
              '#title' => $task->label(),
              '#url' => $task->toUrl(),
            ],
            'status' => [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#value' => $status,
              '#attributes' => ['class' => ['hosting-task-status']],
            ],
          ];
          $task_tags = Cache::mergeTags($task_tags, $task->getCacheTags());
        }
      }
    }
    catch (\Exception) {
      // Entity type may not exist yet.
    }

    $build = [
      '#theme' => 'hosting_task_queue',
      '#title' => $this->t('Task queue'),
      '#list' => [
        '#theme' => 'item_list',
        '#items' => $task_items,
        '#empty' => $this->t('No recent tasks.'),
        '#attributes' => ['class' => ['hosting-task-queue']],
      ],
      '#attributes' => ['class' => ['hosting-panel', 'hosting-panel--task-queue']],
    ];

    if ($task_tags) {
      $build['#cache']['tags'] = $task_tags;
    }

    return $build;
  }

  /**
   * Builds the navigation section.
   */
  protected function buildNavigation(): array {
    $route_name = (string) $this->routeMatch->getRouteName();
    $items = [];

    // Always show these core links.
    $link_defs = [
      ['title' => $this->t('Add a site'), 'route' => 'entity.hosting_site.add_form'],
      ['title' => $this->t('Add a platform'), 'route' => 'entity.hosting_platform.add_form'],
      ['title' => $this->t('Add a server'), 'route' => 'entity.hosting_server.add_form'],
    ];

    // Separator.
    $link_defs[] = ['separator' => TRUE];

    // Collection links (skip the one we're currently on).
    $collections = [
      'entity.hosting_site.collection' => $this->t('Sites'),
      'entity.hosting_platform.collection' => $this->t('Platforms'),
      'entity.hosting_server.collection' => $this->t('Servers'),
      'entity.hosting_client.collection' => $this->t('Clients'),
      'entity.hosting_task.collection' => $this->t('Tasks'),
    ];
    foreach ($collections as $collection_route => $label) {
      if ($route_name !== $collection_route) {
        $link_defs[] = ['title' => $label, 'route' => $collection_route];
      }
    }

    // Admin links.
    $link_defs[] = ['separator' => TRUE];
    $link_defs[] = ['title' => $this->t('Queue management'), 'route' => 'hosting.admin.queues'];
    $link_defs[] = ['title' => $this->t('Hosting features'), 'route' => 'hosting.admin.features'];
    $link_defs[] = ['title' => $this->t('Hosting settings'), 'route' => 'hosting.admin.settings'];

    foreach ($link_defs as $def) {
      if (!empty($def['separator'])) {
        $items[] = [
          '#markup' => '<hr class="hosting-nav-separator">',
        ];
        continue;
      }

      try {
        $url = Url::fromRoute($def['route']);
        if ($url->access()) {
          $items[] = [
            '#type' => 'link',
            '#title' => $def['title'],
            '#url' => $url,
          ];
        }
      }
      catch (\Exception) {
        // Route may not exist.
      }
    }

    return [
      '#theme' => 'hosting_navigation',
      '#title' => $this->t('Navigation'),
      '#list' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['class' => ['hosting-navigation']],
      ],
      '#attributes' => ['class' => ['hosting-panel', 'hosting-panel--navigation']],
    ];
  }

  /**
   * Builds the queue summary section.
   */
  protected function buildQueueSummary(): array {
    $rows = [];

    try {
      $queues = $this->queueDispatcher->getQueuesWithState();

      foreach ($queues as $queue_id => $queue) {
        $status = $queue['enabled'] ? $this->t('Active') : $this->t('Disabled');
        $status_class = $queue['enabled'] ? 'hosting-success' : 'hosting-disable';

        $last_run = $queue['last_run']
          ? \Drupal::service('date.formatter')->formatTimeDiffSince($queue['last_run']) . ' ' . $this->t('ago')
          : $this->t('Never');

        $rows[] = [
          'data' => [
            $queue['label'],
            [
              'data' => ['#markup' => '<strong class="' . $status_class . '">' . $status . '</strong>'],
            ],
            $queue['total_items'],
            $last_run,
          ],
        ];
      }
    }
    catch (\Exception) {
      // Queue dispatcher may not be available.
    }

    if (empty($rows)) {
      return [];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['hosting-panel', 'hosting-panel--queues']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Queues'),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Queue'),
          $this->t('Status'),
          $this->t('Items'),
          $this->t('Last run'),
        ],
        '#rows' => $rows,
        '#attributes' => ['class' => ['hosting-table', 'hosting-queue-summary']],
      ],
    ];
  }

}
