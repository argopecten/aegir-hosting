<?php
/**
 * @file
 * Hosting service class for the Hosting web pack module.
 */

\Drupal::moduleHandler()->loadInclude('hosting_web_server', 'service.inc');

class hostingService_http_pack extends hostingService_http {
  public $type = 'pack';
  public $name = 'Web Pack';

  function view(&$render) {
    $render['master_servers'] = array(
      '#type' => 'item',
      '#title' => t('Master web servers'),
      '#markup' => \Drupal::service('theme.manager')->render('item_list', array('items' => array_map('_hosting_node_link', array_intersect_key($this->master_servers, hosting_get_servers('http'))))),
    );
    $render['slave_servers'] = array(
      '#type' => 'item',
      '#title' => t('Slave web servers'),
      '#markup' => \Drupal::service('theme.manager')->render('item_list', array('items' => array_map('_hosting_node_link', array_intersect_key($this->slave_servers, hosting_get_servers('http'))))),
    );
  }

  function form(&$form) {
    parent::form($form);

    $servers = hosting_get_servers('http');
    $server_id = $this->getServerId();
    if ($server_id) {
      unset($servers[$server_id]);
    }

    $form['master_servers'] = array(
      '#title' => t('Master servers'),
      '#type' => 'checkboxes',
      '#options' => $servers,
      '#default_value' => isset($this->master_servers) ? $this->master_servers : array(),
    );
    $form['slave_servers'] = array(
      '#title' => t('Slave servers'),
      '#type' => 'checkboxes',
      '#options' => $servers,
      '#default_value' => isset($this->slave_servers) ? $this->slave_servers : array(),
    );
  }

  function load() {
    parent::load();

    $ssl_enabled = TRUE;

    $master_servers = array();
    $slave_servers = array();
    $server_id = $this->getServerId();
    $result = \Drupal::database()->query('SELECT web_server_id, type FROM {hosting_web_pack} WHERE server_id = :server_id', array(':server_id' => $server_id));
    while ($web_server = $result->fetch()) {
      if ($web_server->type == 'master') {
        $master_servers[] = $web_server->web_server_id;
      }
      else {
        $slave_servers[] = $web_server->web_server_id;
      }
      $server = hosting_entity_load_any($web_server->web_server_id);
      if ($server) {
        $http_service = hosting_server_service($server, 'http');
        if ($http_service) {
          $ssl_enabled = $ssl_enabled && $http_service->ssl_enabled;
        }
      }
    }

    $this->ssl_enabled = $ssl_enabled;
    $this->setValues(array('master_servers' => \Drupal\Component\Utility\MapArray::create($master_servers)));
    $this->setValues(array('slave_servers' => \Drupal\Component\Utility\MapArray::create($slave_servers)));
  }

  function insert() {
    parent::insert();
    foreach (array_filter($this->master_servers) as $web_server) {
      $record = array(
        'server_id' => $this->getServerId(),
        'web_server_id' => $web_server,
        'type' => 'master',
      );
      \Drupal::database()->insert('hosting_web_pack')
        ->fields($record)
        ->execute();
    }
    foreach (array_filter($this->slave_servers) as $web_server) {
      $record = array(
        'server_id' => $this->getServerId(),
        'web_server_id' => $web_server,
        'type' => 'slave',
      );
      \Drupal::database()->insert('hosting_web_pack')
        ->fields($record)
        ->execute();
    }
  }

  function delete() {
    parent::delete();
    \Drupal::database()->delete('hosting_web_pack')
      ->condition('server_id', $this->getServerId())
      ->execute();
  }

  function delete_revision() {
    parent::delete_revision();
    \Drupal::database()->delete('hosting_web_pack')
      ->condition('server_id', $this->getServerId())
      ->execute();
  }

  function context_options($task_type, $ref_type, &$task) {
    parent::context_options($task_type, $ref_type, $task);

    $task->context_options['master_web_servers'] = implode(',', array_values(array_map('hosting_context_name', array_intersect_key($this->master_servers, hosting_get_servers('http')))));
    $task->context_options['slave_web_servers'] = implode(',', array_values(array_map('hosting_context_name', array_intersect_key($this->slave_servers, hosting_get_servers('http')))));
  }

  function context_import($context) {
    $master_servers = $slave_server = array();

    foreach ($context->master_web_servers as $master_server_context) {
      $server_id = hosting_context_entity_id($master_server_context);
      $master_servers[$server_id] = $server_id;
    }
    $this->master_servers = $master_servers;

    foreach ($context->slave_web_servers as $slave_server_context) {
      $server_id = hosting_context_entity_id($slave_server_context);
      $slave_servers[$server_id] = $server_id;
    }
    $this->slave_servers = $slave_servers;
  }

}
