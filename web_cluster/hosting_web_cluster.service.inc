<?php
/**
 * @file
 * Extensions to the Hosting service class for the web cluster module.
 */

\Drupal::moduleHandler()->loadInclude('hosting_web_server', 'service.inc');

class hostingService_http_cluster extends hostingService_http {
  public $type = 'cluster';
  public $name = 'Web Cluster';

  function view(&$render) {
    $render['web_servers'] = array(
      '#type' => 'item',
      '#title' => t('Web servers'),
      '#markup' => \Drupal::service('theme.manager')->render('item_list', array('items' => array_map('_hosting_node_link', array_intersect_key($this->web_servers, hosting_get_servers('http'))))),
    );
  }

  function form(&$form) {
    parent::form($form);

    $servers = hosting_get_servers('http');
    $server_id = $this->getServerId();
    if ($server_id) {
      unset($servers[$server_id]);
    }

    $form['web_servers'] = array(
      '#title' => t('Servers'),
      '#type' => 'checkboxes',
      '#options' => $servers,
      '#default_value' => isset($this->web_servers) ? $this->web_servers : array(),
    );
  }

  function load() {
    parent::load();

    $ssl_enabled = TRUE;

    $web_servers = array();
    $server_id = $this->getServerId();
    $result = \Drupal::database()->query('SELECT web_server_id FROM {hosting_web_cluster} WHERE server_id = :server_id', array(':server_id' => $server_id));
    while ($web_server = $result->fetch()) {
      $web_servers[] = $web_server->web_server_id;
      $server = hosting_entity_load_any($web_server->web_server_id);
      if ($server) {
        $http_service = hosting_server_service($server, 'http');
        if ($http_service) {
          $ssl_enabled = $ssl_enabled && $http_service->ssl_enabled;
        }
      }
    }

    $this->ssl_enabled = $ssl_enabled;
    $this->setValues(array('web_servers' => \Drupal\Component\Utility\MapArray::create($web_servers)));
  }

  function insert() {
    parent::insert();
    foreach (array_filter($this->web_servers) as $web_server) {
      $record = array(
        'server_id' => $this->getServerId(),
        'web_server_id' => $web_server,
      );
      \Drupal::database()->insert('hosting_web_cluster')
        ->fields($record)
        ->execute();
    }
  }

  function delete() {
    parent::delete();
    \Drupal::database()->delete('hosting_web_cluster')
      ->condition('server_id', $this->getServerId())
      ->execute();
  }

  function delete_revision() {
    parent::delete_revision();
    \Drupal::database()->delete('hosting_web_cluster')
      ->condition('server_id', $this->getServerId())
      ->execute();
  }

  function context_options($task_type, $ref_type, &$task) {
    parent::context_options($task_type, $ref_type, $task);

    $task->context_options['cluster_web_servers'] = implode(',', array_values(array_map('hosting_context_name', array_intersect_key($this->web_servers, hosting_get_servers('http')))));
  }
}
