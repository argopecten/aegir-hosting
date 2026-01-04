<?php

namespace Drupal\hosting\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Redirect entity collection routes to their View listings.
 */
class HostingRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $redirects = [
      'entity.hosting_site.collection' => [
        'target' => 'view.hosting_site_list.page_sites',
        'path' => '/hosting/sites/list',
      ],
      'entity.hosting_platform.collection' => [
        'target' => 'view.hosting_platform_list.page_platforms',
        'path' => '/hosting/platforms/list',
      ],
      'entity.hosting_server.collection' => [
        'target' => 'view.hosting_server_list.page',
        'path' => '/hosting/servers/list',
      ],
      'entity.hosting_client.collection' => [
        'target' => 'view.hosting_client_list.page_clients',
        'path' => '/hosting/clients/list',
      ],
      'entity.hosting_task.collection' => [
        'target' => 'view.hosting_task_list.page_global',
        'path' => '/hosting/tasks/list',
      ],
    ];

    foreach ($redirects as $route_name => $info) {
      if ($route = $collection->get($route_name)) {
        $route->setDefault('_controller', 'Drupal\\hosting\\Controller\\HostingRedirectController::redirectToView');
        $route->setDefault('redirect_route', $info['target']);
        $route->setDefault('redirect_parameters', []);
        $route->setDefault('redirect_options', []);
        $route->setDefault('redirect_status', 302);
        $route->setPath($info['path']);
      }
    }

    foreach ([
      'entity.hosting_site.add_form',
      'entity.hosting_platform.add_form',
      'entity.hosting_server.add_form',
      'entity.hosting_client.add_form',
    ] as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->setOption('_admin_route', FALSE);
      }
    }
  }

}
