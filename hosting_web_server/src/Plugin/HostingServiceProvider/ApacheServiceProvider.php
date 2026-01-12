<?php

namespace Drupal\hosting_web_server\Plugin\HostingServiceProvider;

use Drupal\hosting_server\Plugin\HostingServiceProviderBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Apache HTTP service provider.
 *
 * @HostingServiceProvider(
 *   id = "apache",
 *   label = @Translation("Apache"),
 *   service_type = "http"
 * )
 */
class ApacheServiceProvider extends HostingServiceProviderBase {

  public function defaultConfiguration(): array {
    return [
      'port' => 80,
      'restart_cmd' => $this->defaultRestartCommand(),
    ];
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $config = $this->getConfiguration() + $this->defaultConfiguration();

    $form['port'] = [
      '#type' => 'number',
      '#title' => $this->t('Port'),
      '#default_value' => $config['port'],
      '#min' => 1,
    ];
    $form['restart_cmd'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Restart command'),
      '#default_value' => $config['restart_cmd'],
    ];

    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->setConfiguration([
      'port' => (int) $form_state->getValue('port'),
      'restart_cmd' => (string) $form_state->getValue('restart_cmd'),
    ]);
  }

  protected function defaultRestartCommand(): string {
    $candidates = [
      '/usr/sbin/apache2ctl',
      '/usr/sbin/apachectl',
      '/usr/local/sbin/apachectl',
    ];
    foreach ($candidates as $candidate) {
      if (is_executable($candidate)) {
        return 'sudo ' . $candidate . ' graceful';
      }
    }
    return 'sudo /usr/sbin/apache2ctl graceful';
  }

}
