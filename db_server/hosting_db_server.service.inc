<?php
/**
 * @file
 * Provide the hosting serivce classes for database integration.
 */

/**
 * The base db service type class.
 */
class hostingService_db extends hostingService {
  public $service = 'db';
}

/**
 * A MySQL specific db service implementation class.
 */
class hostingService_db_mysql extends hostingService_db {
  public $type = 'mysql';
  public $name = 'MySQL';
  public $has_port = TRUE;

  function default_port() {
    return 3306;
  }

  function form(&$form) {
    parent::form($form);
    $form['db_user'] = array(
      '#type' => 'textfield',
      '#required' => !empty($this->available),
      '#title' => t('Username'),
      '#description' => t('The user that will be used to create new mysql users and databases for new sites.'),
      '#size' => 40,
      '#default_value' => isset($this->db_user) ? $this->db_user : NULL,
      '#maxlength' => 64,
      '#weight' => 5,
    );
    $passwd_description = '';
    if (isset($this->db_passwd)) {
      $passwd_description = t('<strong>You have already set a password for this database server.</strong><br />');
    }
    $form['db_passwd'] = array(
      '#type' => 'password_confirm',
      '#required' => FALSE,
      '#description' => $passwd_description . t('The password for the user that will be used to create new mysql users and databases for new sites'),
      '#size' => 30,
      '#weight' => 10,
    );
  }

  function insert() {
    parent::insert();
    $server_id = $this->getServerId();
    $id = \Drupal::database()->insert('hosting_db_server')
      ->fields(array(
        'server_id' => $server_id,
        'db_user' => $this->db_user,
        'db_passwd' => $this->db_passwd,
      ))
      ->execute();
  }

  function update() {
    if (!empty($this->db_passwd)) {
      parent::update();
    }
    else {
      // only do the parent's update routine.
      parent::delete_revision();
      parent::insert();
    }
  }

  function delete_revision() {
    parent::delete_revision();
    $server_id = $this->getServerId();
    \Drupal::database()->delete('hosting_db_server')
      ->condition('server_id', $server_id)
      ->execute();
  }

  function delete() {
    parent::delete();
    $server_id = $this->getServerId();
    \Drupal::database()->delete('hosting_db_server')
      ->condition('server_id', $server_id)
      ->execute();
  }


  function load() {
    parent::load();
    $server_id = $this->getServerId();
    $this->mergeData('SELECT db_user, db_passwd FROM {hosting_db_server} WHERE server_id = :server_id', array(':server_id' => $server_id));
  }

  function view(&$render) {
    parent::view($render);

    $render['db_user'] = array(
      '#type' => 'item',
      '#title' => t('Database user'),
      '#markup' => \Drupal\Component\Utility\Xss::filter($this->db_user),
      '#weight' => 1,
    );

    if (empty($this->db_passwd)) {
      $markup = t('None');
    }
    else {
      $markup = t('Saved');
    }
    $render['db_passwd'] = array(
        '#type' => 'item',
        '#title' => t('Database password'),
        '#markup' => $markup,
        '#weight' => 2,
    );
  }

  public function context_options($task_type, $ref_type, &$task) {
    parent::context_options($task_type, $ref_type, $task);

    // Provide context_options for verification and writing out to an alias
    $task->context_options['master_db'] = 'mysql' . '://' . urlencode($this->db_user) . ':' . urlencode($this->db_passwd) . '@' . $this->server->label;
  }

  public function context_import($context) {
    parent::context_import($context);

    $matches = array();
    preg_match("+^mysql://(.*):(.*)@.*$+", stripslashes($context->master_db), $matches);
    $this->db_user = urldecode($matches[1]);
    $this->db_passwd = urldecode($matches[2]);
  }
}
