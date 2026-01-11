<?php

namespace Drupal\hosting_web_server\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Hosting Web Server settings.
 */
class HostingWebServerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hosting_web_server_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['hosting_web_server.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('hosting_web_server.settings');

    $form['hosting_web_server_default_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Default web server type'),
      '#description' => $this->t('Select the default web server type for new servers.'),
      '#options' => [
        'apache' => $this->t('Apache'),
        'apache_ssl' => $this->t('Apache with SSL'),
        'nginx' => $this->t('Nginx'),
        'nginx_ssl' => $this->t('Nginx with SSL'),
      ],
      '#default_value' => $config->get('default_type') ?? 'apache',
    ];

    $form['hosting_web_server_default_port'] = [
      '#type' => 'number',
      '#title' => $this->t('Default HTTP port'),
      '#description' => $this->t('The default port for HTTP connections.'),
      '#default_value' => $config->get('default_port') ?? 80,
      '#min' => 1,
      '#max' => 65535,
    ];

    $form['hosting_web_server_default_ssl_port'] = [
      '#type' => 'number',
      '#title' => $this->t('Default HTTPS port'),
      '#description' => $this->t('The default port for HTTPS connections.'),
      '#default_value' => $config->get('default_ssl_port') ?? 443,
      '#min' => 1,
      '#max' => 65535,
    ];

    $form['hosting_web_server_restart_cmd'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default restart command'),
      '#description' => $this->t('The default command to restart the web server. Use %server for the server name.'),
      '#default_value' => $config->get('restart_cmd') ?? 'sudo /etc/init.d/apache2 reload',
      '#maxlength' => 255,
    ];

    $form['hosting_web_server_config_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Configuration path'),
      '#description' => $this->t('The default path where web server configuration files are stored.'),
      '#default_value' => $config->get('config_path') ?? '/var/aegir/config/server_master/apache/vhost.d',
      '#maxlength' => 255,
    ];

    $form['hosting_web_server_script_user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Script user'),
      '#description' => $this->t('The user that runs the web server scripts.'),
      '#default_value' => $config->get('script_user') ?? 'aegir',
      '#maxlength' => 64,
    ];

    $form['hosting_web_server_backup_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable configuration backups'),
      '#description' => $this->t('Keep backups of web server configuration files.'),
      '#default_value' => $config->get('backup_enabled') ?? TRUE,
    ];

    $form['hosting_web_server_backup_retention'] = [
      '#type' => 'number',
      '#title' => $this->t('Backup retention days'),
      '#description' => $this->t('Number of days to keep configuration backups.'),
      '#default_value' => $config->get('backup_retention') ?? 30,
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':input[name="hosting_web_server_backup_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('hosting_web_server.settings')
      ->set('default_type', $form_state->getValue('hosting_web_server_default_type'))
      ->set('default_port', $form_state->getValue('hosting_web_server_default_port'))
      ->set('default_ssl_port', $form_state->getValue('hosting_web_server_default_ssl_port'))
      ->set('restart_cmd', $form_state->getValue('hosting_web_server_restart_cmd'))
      ->set('config_path', $form_state->getValue('hosting_web_server_config_path'))
      ->set('script_user', $form_state->getValue('hosting_web_server_script_user'))
      ->set('backup_enabled', $form_state->getValue('hosting_web_server_backup_enabled'))
      ->set('backup_retention', $form_state->getValue('hosting_web_server_backup_retention'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
