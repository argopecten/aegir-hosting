<?php
/**
 * @file
 * Define the base Hosting service class.
 */

class hostingService {

  public $server;

  // The full server entity attached to this service.
  public $server_node;

  // The service this class represents. ie 'http', 'db'
  public $service = '';

  // The service type for this class.  ie 'mysql', 'apache'
  public $type = '';

  // The human readable name for this service type.  ie 'MySQL', 'Apache'
  public $name = '';

  // Whether or not the service is available.
  public $available = 0;

  protected $has_restart_cmd = FALSE;
  protected $has_port = FALSE;

  function __construct($node, $values = NULL) {
    self::save($node);
    self::setValues($values);
  }

  /**
   * Returns human readable name for this service.
   */
  public function getName() {
    if (empty($this->name)) {
      return $this->type;
    }
    else {
      return $this->name;
    }
  }

  public function has_port() {
    return $this->has_port;
  }

  public function has_restart_cmd() {
    return $this->has_restart_cmd;
  }

  protected function getServerId() {
    if ($this->server_node instanceof \Drupal\Core\Entity\EntityInterface) {
      return (int) $this->server_node->id();
    }
    if (is_object($this->server) && isset($this->server->id)) {
      return (int) $this->server->id;
    }
    return NULL;
  }

  public function load() {
    $server_id = $this->getServerId();
    $this->mergeData("SELECT port, restart_cmd, available
                      FROM {hosting_service}
                      WHERE server_id = :server_id
                      AND type = :type",
                      array(
                        ':server_id' => $server_id,
                        ':type' => $this->type
                      )
                    );
  }

  protected function mergeData($query, $args = array()) {
    $result = \Drupal::database()->query($query, $args)->fetchAssoc();
    $this->setValues($result);
  }

  public function setValues($record = NULL) {
    if (is_array($record)) {
      foreach ($record as $key => $value) {
        $this->{$key} = $value;
      }
    }
  }

  public function save($node) {
    $server_id = hosting_entity_id($node);
    if ($server_id) {
      $this->server_node = $node;
      $this->server = new stdClass();
      $this->server->id = $server_id;
      $this->server->label = hosting_entity_label($node);
    }
  }

  public function default_restart_cmd() {
    return '';
  }

  public function default_port() {
    return 0;
  }

  public function insert() {
    $server_id = $this->getServerId();
    $id = \Drupal::database()->insert('hosting_service')
      ->fields(array(
        'server_id' => $server_id,
        'service' => $this->service,
        'type' => $this->type,
        'port' => $this->port,
        'restart_cmd' => isset($this->restart_cmd) ? $this->restart_cmd : '',
        'available' => $this->available,
      ))
      ->execute();
  }

  public function update() {
    $this->delete_revision();
    $this->insert();
  }

  public function delete() {
    $server_id = $this->getServerId();
    \Drupal::database()->delete('hosting_service')
      ->condition('service', $this->service)
      ->condition('server_id', $server_id)
      ->execute();
  }

  public function delete_revision() {
    $server_id = $this->getServerId();
    \Drupal::database()->delete('hosting_service')
      ->condition('service', $this->service)
      ->condition('server_id', $server_id)
      ->execute();
  }

  public function form(&$form) {
    if ($this->has_restart_cmd) {
      $form['restart_cmd'] = array(
        '#type' => 'textfield',
        '#title' => t('Restart command'),
        '#required' => !empty($this->available),
        '#description' => t('The command to run to restart this service.'),
        '#default_value' => (isset($this->restart_cmd)) ? $this->restart_cmd : $this->default_restart_cmd(),
        '#size' => 40,
        '#maxlength' => 255,
        '#weight' => -7,
      );
    }
    else {
      $form['restart_cmd'] = array(
        '#type' => 'value',
        '#value' => NULL,
      );
    }


    if ($this->has_port) {
      $form['port'] = array(
        '#type' => 'textfield',
        '#title' => t('Port'),
        '#required' => !empty($this->available),
        '#size' => 40,
        '#default_value' => isset($this->port) ? $this->port : $this->default_port(),
        '#description' => t("The port that this service is listening on."),
        '#maxlength' => 255,
        '#weight' => -8,
      );
    }
    else {
      $form['port'] = array(
        '#type' => 'value',
        '#value' => '0',
      );
    }

  }

  public function view(&$render) {
    if ($this->has_restart_cmd) {
      $render['restart_cmd'] = array(
        '#type' => 'item',
        '#title' => t('Restart command'),
        '#markup' => \Drupal\Component\Utility\Xss::filter($this->restart_cmd),
      );

    }

    if ($this->has_port) {
      $render['port'] = array(
        '#type' => 'item',
        '#title' => t('Port'),
        '#markup' => \Drupal\Component\Utility\Xss::filter($this->port),
      );
    }
  }



  public function validate(&$node, &$form, &$form_state) {
    if ($this->has_port) {
      if ((int) $this->port <= 0) {
        if ($form_state instanceof \Drupal\Core\Form\FormStateInterface) {
          $form_state->setErrorByName('port', t("The port you specify must be a number."));
        }
        else {
          \Drupal::messenger()->addError(t("The port you specify must be a number."));
        }
      }
    }
  }

  public function context_options($task_type, $ref_type, &$task) {
    $task->context_options[$this->service . '_service_type'] = $this->type;

    if ($this->has_restart_cmd) {
      $task->context_options[$this->service . '_restart_cmd'] = $this->restart_cmd;
    }

    if ($this->has_port) {
      $task->context_options[$this->service . '_port'] = $this->port;
    }
  }

  public function context_import($context) {
    $this->available = 1;

    if ($this->has_port) {
      $this->port = $context->{$this->service . '_port'};
    }

    if ($this->has_restart_cmd) {
      $this->restart_cmd = $context->{$this->service . '_restart_cmd'};
    }
  }

}
