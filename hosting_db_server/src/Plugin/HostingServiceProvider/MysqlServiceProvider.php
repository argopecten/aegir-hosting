<?php

namespace Drupal\hosting_db_server\Plugin\HostingServiceProvider;

use Drupal\hosting_server\Plugin\HostingServiceProviderBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * MySQL service provider.
 *
 * @HostingServiceProvider(
 *   id = "mysql",
 *   label = @Translation("MySQL"),
 *   service_type = "db"
 * )
 */
class MysqlServiceProvider extends HostingServiceProviderBase {

  public function defaultConfiguration(): array {
    return [
      'port' => 3306,
      'db_user' => '',
      'db_passwd' => '',
    ];
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $config = $this->getConfiguration() + $this->defaultConfiguration();

    $form['db_user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database user'),
      '#default_value' => $config['db_user'],
      '#required' => TRUE,
    ];
    $form['db_passwd'] = [
      '#type' => 'password',
      '#title' => $this->t('Database password'),
      '#description' => $this->t('Leave blank to keep the existing password.'),
      '#default_value' => '',
    ];
    $form['port'] = [
      '#type' => 'number',
      '#title' => $this->t('Port'),
      '#default_value' => $config['port'],
      '#min' => 1,
    ];

    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $existing = $this->getConfiguration();
    $password = (string) $form_state->getValue('db_passwd');
    if ($password === '') {
      $password = (string) ($existing['db_passwd'] ?? '');
    }

    $this->setConfiguration([
      'db_user' => (string) $form_state->getValue('db_user'),
      'db_passwd' => $password,
      'port' => (int) $form_state->getValue('port'),
    ]);
  }

}
