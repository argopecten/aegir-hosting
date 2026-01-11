<?php

namespace Drupal\hosting_db_server\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Hosting Database Server settings.
 */
class HostingDbServerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hosting_db_server_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['hosting_db_server.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('hosting_db_server.settings');

    $form['hosting_db_server_default_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Default database server type'),
      '#description' => $this->t('Select the default database server type for new database servers.'),
      '#options' => [
        'mysql' => $this->t('MySQL'),
        'mariadb' => $this->t('MariaDB'),
        'pgsql' => $this->t('PostgreSQL'),
      ],
      '#default_value' => $config->get('default_type') ?? 'mysql',
    ];

    $form['hosting_db_server_default_port'] = [
      '#type' => 'number',
      '#title' => $this->t('Default database port'),
      '#description' => $this->t('The default port for database connections (MySQL: 3306, PostgreSQL: 5432).'),
      '#default_value' => $config->get('default_port') ?? 3306,
      '#min' => 1,
      '#max' => 65535,
    ];

    $form['hosting_db_server_connection_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Connection timeout'),
      '#description' => $this->t('Timeout in seconds for database connection attempts.'),
      '#default_value' => $config->get('connection_timeout') ?? 30,
      '#min' => 1,
    ];

    $form['hosting_db_server_max_connections'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum connections'),
      '#description' => $this->t('Maximum number of concurrent database connections allowed per server.'),
      '#default_value' => $config->get('max_connections') ?? 100,
      '#min' => 1,
    ];

    $form['hosting_db_server_backup'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Backup settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['hosting_db_server_backup']['hosting_db_server_backup_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable automatic database backups'),
      '#description' => $this->t('Automatically backup databases on a schedule.'),
      '#default_value' => $config->get('backup_enabled') ?? TRUE,
    ];

    $form['hosting_db_server_backup']['hosting_db_server_backup_schedule'] = [
      '#type' => 'select',
      '#title' => $this->t('Backup schedule'),
      '#description' => $this->t('How often to perform automatic backups.'),
      '#options' => [
        'hourly' => $this->t('Hourly'),
        'daily' => $this->t('Daily'),
        'weekly' => $this->t('Weekly'),
        'monthly' => $this->t('Monthly'),
      ],
      '#default_value' => $config->get('backup_schedule') ?? 'daily',
      '#states' => [
        'visible' => [
          ':input[name="hosting_db_server_backup_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_db_server_backup']['hosting_db_server_backup_retention'] = [
      '#type' => 'number',
      '#title' => $this->t('Backup retention days'),
      '#description' => $this->t('Number of days to keep database backups.'),
      '#default_value' => $config->get('backup_retention') ?? 30,
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':input[name="hosting_db_server_backup_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_db_server_backup']['hosting_db_server_backup_compression'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Compress backups'),
      '#description' => $this->t('Use gzip compression for database backups.'),
      '#default_value' => $config->get('backup_compression') ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="hosting_db_server_backup_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_db_server_security'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Security settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['hosting_db_server_security']['hosting_db_server_require_ssl'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require SSL connections'),
      '#description' => $this->t('Force all database connections to use SSL.'),
      '#default_value' => $config->get('require_ssl') ?? FALSE,
    ];

    $form['hosting_db_server_security']['hosting_db_server_password_complexity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enforce password complexity'),
      '#description' => $this->t('Require strong passwords for database users.'),
      '#default_value' => $config->get('password_complexity') ?? TRUE,
    ];

    $form['hosting_db_server_monitoring'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Monitoring settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['hosting_db_server_monitoring']['hosting_db_server_monitor_performance'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Monitor database performance'),
      '#description' => $this->t('Track query performance and database metrics.'),
      '#default_value' => $config->get('monitor_performance') ?? TRUE,
    ];

    $form['hosting_db_server_monitoring']['hosting_db_server_slow_query_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Slow query threshold (seconds)'),
      '#description' => $this->t('Log queries that take longer than this threshold.'),
      '#default_value' => $config->get('slow_query_threshold') ?? 1,
      '#min' => 0,
      '#step' => 0.1,
      '#states' => [
        'visible' => [
          ':input[name="hosting_db_server_monitor_performance"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('hosting_db_server.settings')
      ->set('default_type', $form_state->getValue('hosting_db_server_default_type'))
      ->set('default_port', $form_state->getValue('hosting_db_server_default_port'))
      ->set('connection_timeout', $form_state->getValue('hosting_db_server_connection_timeout'))
      ->set('max_connections', $form_state->getValue('hosting_db_server_max_connections'))
      ->set('backup_enabled', $form_state->getValue('hosting_db_server_backup_enabled'))
      ->set('backup_schedule', $form_state->getValue('hosting_db_server_backup_schedule'))
      ->set('backup_retention', $form_state->getValue('hosting_db_server_backup_retention'))
      ->set('backup_compression', $form_state->getValue('hosting_db_server_backup_compression'))
      ->set('require_ssl', $form_state->getValue('hosting_db_server_require_ssl'))
      ->set('password_complexity', $form_state->getValue('hosting_db_server_password_complexity'))
      ->set('monitor_performance', $form_state->getValue('hosting_db_server_monitor_performance'))
      ->set('slow_query_threshold', $form_state->getValue('hosting_db_server_slow_query_threshold'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
