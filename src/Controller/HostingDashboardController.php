<?php

namespace Drupal\hosting\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the Hosting Dashboard overview page.
 *
 * Builds a #theme => 'hosting_dashboard' render array with summary cards,
 * site table, recent tasks, and quick links — D7 Aegir dashboard style.
 */
class HostingDashboardController extends ControllerBase {

  /**
   * The icon sprite URL.
   */
  protected string $iconSpritePath;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    try {
      $theme_path = $container->get('extension.list.theme')->getPath('aegir_eldir');
    }
    catch (\Throwable) {
      $theme_path = 'themes/contrib/aegir-eldir';
    }
    $instance->iconSpritePath = base_path() . $theme_path . '/images/svg/aegir-icons-sprite.svg';
    return $instance;
  }

  /**
   * Renders the hosting dashboard.
   *
   * @return array
   *   A render array using #theme => 'hosting_dashboard'.
   */
  public function dashboard(): array {
    return [
      '#theme' => 'hosting_dashboard',
      '#summary_cards' => $this->buildSummaryCards(),
      '#site_table' => $this->buildSiteTable(),
      '#attached' => [
        'library' => ['hosting/common'],
      ],
      '#cache' => [
        'tags' => [
          'hosting_site_list',
          'hosting_platform_list',
          'hosting_server_list',
          'hosting_task_list',
        ],
        'max-age' => 60,
      ],
    ];
  }

  /**
   * Builds summary cards data for the dashboard template.
   *
   * @return array
   *   Array of card data: type, label, count, url.
   */
  protected function buildSummaryCards(): array {
    $cards = [];

    $definitions = [
      ['entity_type' => 'hosting_site', 'type' => 'sites', 'label' => $this->t('Sites'), 'route' => 'entity.hosting_site.collection'],
      ['entity_type' => 'hosting_platform', 'type' => 'platforms', 'label' => $this->t('Platforms'), 'route' => 'entity.hosting_platform.collection'],
      ['entity_type' => 'hosting_server', 'type' => 'servers', 'label' => $this->t('Servers'), 'route' => 'entity.hosting_server.collection'],
      ['entity_type' => 'hosting_task', 'type' => 'tasks', 'label' => $this->t('Tasks'), 'route' => 'entity.hosting_task.collection'],
    ];

    foreach ($definitions as $def) {
      try {
        $count = (int) $this->entityTypeManager
          ->getStorage($def['entity_type'])
          ->getQuery()
          ->accessCheck(TRUE)
          ->count()
          ->execute();
      }
      catch (\Exception) {
        $count = 0;
      }

      $cards[] = [
        'type' => $def['type'],
        'label' => $def['label'],
        'count' => $count,
        'url' => Url::fromRoute($def['route'])->toString(),
      ];
    }

    return $cards;
  }

  /**
   * Builds the site listing table for the main content area.
   *
   * Mirrors the D7 /hosting/sites table with domain, status, platform.
   *
   * @return array
   *   A render array for the site table.
   */
  protected function buildSiteTable(): array {
    $header = [
      '',
      ['data' => $this->t('Site'), 'field' => 'domain'],
      ['data' => $this->t('Profile')],
      ['data' => $this->t('Created')],
      ['data' => $this->t('Platform')],
    ];

    $rows = [];
    $date_formatter = \Drupal::service('date.formatter');

    $icon_map = [
      0 => 'icon-queue',
      1 => 'icon-ok',
      -1 => 'icon-disabled',
      -2 => 'icon-error',
    ];
    $status_css = [0 => 'hosting-queue', 1 => 'hosting-success', -1 => 'hosting-disable', -2 => 'hosting-error'];

    try {
      $site_ids = $this->entityTypeManager
        ->getStorage('hosting_site')
        ->getQuery()
        ->accessCheck(TRUE)
        ->sort('domain', 'ASC')
        ->range(0, 50)
        ->execute();

      if ($site_ids) {
        $sites = $this->entityTypeManager
          ->getStorage('hosting_site')
          ->loadMultiple($site_ids);

        foreach ($sites as $site) {
          $status_val = (int) $site->get('status')->value;

          // SVG icon.
          $icon_name = $icon_map[$status_val] ?? 'icon-queue';
          $icon = Markup::create('<svg class="hosting-icon" aria-hidden="true"><use href="' . $this->iconSpritePath . '#' . $icon_name . '"></use></svg>');

          // Platform name.
          $platform_label = '';
          if ($site->hasField('platform') && !$site->get('platform')->isEmpty()) {
            $platform = $site->get('platform')->entity;
            if ($platform) {
              $platform_label = $platform->label();
            }
          }

          // Profile name.
          $profile_label = '';
          if ($site->hasField('profile') && !$site->get('profile')->isEmpty()) {
            $profile = $site->get('profile')->entity;
            if ($profile) {
              $profile_label = $profile->label();
            }
          }

          // Created date.
          $created = '';
          if ($site->hasField('created') && !$site->get('created')->isEmpty()) {
            $created_ts = (int) $site->get('created')->value;
            if ($created_ts > 0) {
              $created = $date_formatter->formatTimeDiffSince($created_ts) . ' ' . $this->t('ago');
            }
          }

          $rows[] = [
            'data' => [
              ['data' => $icon],
              ['data' => ['#type' => 'link', '#title' => $site->label(), '#url' => $site->toUrl()]],
              $profile_label,
              $created,
              $platform_label,
            ],
            'class' => [$status_css[$status_val] ?? ''],
          ];
        }
      }
    }
    catch (\Exception) {
      // Entity type may not exist yet.
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No sites have been created yet.'),
      '#attributes' => ['class' => ['hosting-table', 'hosting-site-list']],
    ];
  }

  /**
   * Builds the recent tasks list for the sidebar.
   *
   * @return array
   *   A render array for recent tasks.
   */
  protected function buildRecentTasks(): array {
    $rows = [];

    try {
      $task_ids = $this->entityTypeManager
        ->getStorage('hosting_task')
        ->getQuery()
        ->accessCheck(TRUE)
        ->sort('id', 'DESC')
        ->range(0, 10)
        ->execute();

      if ($task_ids) {
        $tasks = $this->entityTypeManager
          ->getStorage('hosting_task')
          ->loadMultiple($task_ids);

        foreach ($tasks as $task) {
          $status = (string) $task->get('status')->value;
          $status_css = match ($status) {
            'success' => 'hosting-success',
            'error' => 'hosting-error',
            'warning' => 'hosting-warning',
            'processing' => 'hosting-info',
            'queued' => 'hosting-queue',
            default => '',
          };
          $icon_name = match ($status) {
            'success' => 'icon-ok',
            'error' => 'icon-error',
            'warning' => 'icon-warning',
            'processing' => 'icon-info',
            'queued' => 'icon-queue',
            default => 'icon-queue',
          };
          $icon = Markup::create('<svg class="hosting-icon" aria-hidden="true"><use href="' . $this->iconSpritePath . '#' . $icon_name . '"></use></svg>');

          $rows[] = [
            'data' => [
              ['data' => $icon],
              ['data' => ['#type' => 'link', '#title' => $task->label(), '#url' => $task->toUrl()]],
              ['data' => Markup::create('<strong class="' . $status_css . '">' . ucfirst($status) . '</strong>')],
            ],
            'class' => [$status_css],
          ];
        }
      }
    }
    catch (\Exception) {
      // Entity type may not exist yet.
    }

    return [
      '#type' => 'table',
      '#header' => [
        '',
        $this->t('Task'),
        $this->t('Status'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No tasks yet.'),
      '#attributes' => ['class' => ['hosting-table', 'hosting-task-queue']],
    ];
  }

  /**
   * Builds the quick links section for the sidebar.
   *
   * @return array
   *   A render array of quick action links.
   */
  protected function buildQuickLinks(): array {
    $items = [];

    $link_defs = [
      ['title' => $this->t('Add a site'), 'route' => 'entity.hosting_site.add_form'],
      ['title' => $this->t('Add a platform'), 'route' => 'entity.hosting_platform.add_form'],
      ['title' => $this->t('Add a server'), 'route' => 'entity.hosting_server.add_form'],
      ['title' => $this->t('All tasks'), 'route' => 'entity.hosting_task.collection'],
      ['title' => $this->t('Queue management'), 'route' => 'hosting.admin.queues'],
      ['title' => $this->t('Hosting features'), 'route' => 'hosting.admin.features'],
      ['title' => $this->t('Hosting settings'), 'route' => 'hosting.admin.settings'],
    ];

    foreach ($link_defs as $def) {
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
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['hosting-navigation']],
    ];
  }

}
