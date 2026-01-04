<?php
/**
 * @file
 * Define Hosting service class for SSL.
 */

class hostingService_http_ssl extends hostingService_http_public {

  function load() {
    parent::load();
    $server_id = $this->getServerId();
    $this->mergeData("SELECT ssl_port FROM {hosting_ssl_server} WHERE server_id = :server_id", array(':server_id' => $server_id));
    $this->ssl_enabled = TRUE;
  }

  function insert() {
    parent::insert();
    $server_id = $this->getServerId();
    $id = \Drupal::database()->insert('hosting_ssl_server')
      ->fields(array(
        'server_id' => $server_id,
        'ssl_port' => $this->ssl_port,
      ))
      ->execute();
  }

  function delete() {
    parent::delete();
    $server_id = $this->getServerId();
    \Drupal::database()->delete('hosting_ssl_server')
      ->condition('server_id', $server_id)
      ->execute();
  }

  function delete_revision() {
    parent::delete_revision();
    $server_id = $this->getServerId();
    \Drupal::database()->delete('hosting_ssl_server')
      ->condition('server_id', $server_id)
      ->execute();
  }

  function form(&$form) {
    parent::form($form);

    $form['ssl_port'] = array(
      '#type' => 'textfield',
      '#title' => t('SSL Port'),
      '#required' => !empty($this->available),
      '#size' => 40,
      '#default_value' => isset($this->ssl_port) ? $this->ssl_port : '443',
      '#description' => t("The port that this service will use for encrypted traffic."),
      '#maxlength' => 255,
      '#weight' => -7,
    );
  }

  function view(&$render) {
    parent::view($render);
    $render['ssl_port'] = array(
      '#type' => 'item',
      '#title' => t('SSL Port'),
      '#markup' => \Drupal\Component\Utility\Xss::filter($this->ssl_port),
    );
  }

  public function validate(&$node, &$form, &$form_state) {
    parent::validate($node, $form, $form_state);
    if ((int) $this->ssl_port <= 0) {
      if ($form_state instanceof \Drupal\Core\Form\FormStateInterface) {
        $form_state->setErrorByName('ssl_port', t("The port you specify must be a number."));
      }
      else {
        \Drupal::messenger()->addError(t("The port you specify must be a number."));
      }
    }
  }

  public function context_options($task_type, $ref_type, &$task) {
    parent::context_options($task_type, $ref_type, $task);
    $task->context_options[$this->service . '_ssl_port'] = $this->ssl_port;
  }

  public function context_import($context) {
    parent::context_import($context);

    $this->ssl_port = $context->http_ssl_port;
  }

}

class hostingService_http_apache_ssl extends hostingService_http_ssl {
  public $type = 'apache_ssl';
  public $name = 'Apache SSL';

  protected $has_restart_cmd = TRUE;

  function default_restart_cmd() {
    $systemctl = NULL;
    if (is_executable('/usr/bin/systemctl')) {
      $systemctl = '/usr/bin/systemctl';
    }
    elseif (is_executable('/bin/systemctl')) {
      $systemctl = '/bin/systemctl';
    }
    if ($systemctl) {
      return "sudo $systemctl reload apache2";
    }

    return "sudo $systemctl reload apache2";
  }

}
