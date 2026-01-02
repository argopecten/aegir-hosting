<?php

namespace Drupal\hosting_quota\Controller;

use Drupal\Core\Controller\ControllerBase;

class HostingQuotaController extends ControllerBase {
  /**
   * Render the client quota admin list.
   */
  public function clientList() {
    $items_per_page = 25;
    $all_resources = \Drupal::moduleHandler()->invokeAll('hosting_quota_resource');
    $result = \Drupal::database()->select('hosting_client_quota', 'q');
    $result->join('hosting_client', 'c', 'q.client = c.id');
    $result->fields('q')->fields('c', ['label']);
    $result = $result->extend('PagerDefault')->limit($items_per_page)->execute();
    $output = [];

    while ($row = $result->fetchObject()) {
      $resource = [$row->resource => $all_resources[$row->resource]];

      $output[$row->client][$row->resource]['limit'] = $row->value;
      $output[$row->client][$row->resource]['usage'] = hosting_quota_get_usage($row->client, $resource);
      $output[$row->client][$row->resource]['rendered usage'] = hosting_quota_resource_render($resource, $output[$row->client][$row->resource]['usage']);
      $output[$row->client][$row->resource]['rendered limit'] = hosting_quota_resource_render($resource, $row->value);
      $output[$row->client]['name'] = $row->label;
    }

    return [
      '#theme' => 'hosting_quota_admin_list',
      '#client_quotas' => $output,
      '#resources' => $all_resources,
      '#items_per_page' => $items_per_page,
    ];
  }

}
