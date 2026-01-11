<?php

namespace Drupal\hosting_web_cluster\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Hosting Web Cluster settings.
 */
class HostingWebClusterSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hosting_web_cluster_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['hosting_web_cluster.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('hosting_web_cluster.settings');

    $form['hosting_web_cluster_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable web clustering'),
      '#description' => $this->t('Allow sites to be distributed across multiple web servers.'),
      '#default_value' => $config->get('enabled') ?? FALSE,
    ];

    $form['hosting_web_cluster_load_balancing'] = [
      '#type' => 'select',
      '#title' => $this->t('Load balancing method'),
      '#description' => $this->t('Select the load balancing method for distributing requests.'),
      '#options' => [
        'round_robin' => $this->t('Round Robin'),
        'least_connections' => $this->t('Least Connections'),
        'ip_hash' => $this->t('IP Hash'),
        'weighted' => $this->t('Weighted'),
      ],
      '#default_value' => $config->get('load_balancing') ?? 'round_robin',
      '#states' => [
        'visible' => [
          ':input[name="hosting_web_cluster_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_web_cluster_session_affinity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable session affinity'),
      '#description' => $this->t('Keep user sessions on the same server.'),
      '#default_value' => $config->get('session_affinity') ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="hosting_web_cluster_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_web_cluster_health_check_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Health check interval'),
      '#description' => $this->t('Interval in seconds between server health checks.'),
      '#default_value' => $config->get('health_check_interval') ?? 60,
      '#min' => 10,
      '#states' => [
        'visible' => [
          ':input[name="hosting_web_cluster_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_web_cluster_health_check_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Health check timeout'),
      '#description' => $this->t('Timeout in seconds for health check requests.'),
      '#default_value' => $config->get('health_check_timeout') ?? 10,
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':input[name="hosting_web_cluster_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_web_cluster_sync_method'] = [
      '#type' => 'select',
      '#title' => $this->t('File synchronization method'),
      '#description' => $this->t('Method used to synchronize files across cluster nodes.'),
      '#options' => [
        'rsync' => $this->t('Rsync'),
        'nfs' => $this->t('NFS'),
        'glusterfs' => $this->t('GlusterFS'),
        'ceph' => $this->t('CephFS'),
      ],
      '#default_value' => $config->get('sync_method') ?? 'rsync',
      '#states' => [
        'visible' => [
          ':input[name="hosting_web_cluster_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_web_cluster_auto_failover'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable automatic failover'),
      '#description' => $this->t('Automatically remove unhealthy servers from the cluster.'),
      '#default_value' => $config->get('auto_failover') ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="hosting_web_cluster_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('hosting_web_cluster.settings')
      ->set('enabled', $form_state->getValue('hosting_web_cluster_enabled'))
      ->set('load_balancing', $form_state->getValue('hosting_web_cluster_load_balancing'))
      ->set('session_affinity', $form_state->getValue('hosting_web_cluster_session_affinity'))
      ->set('health_check_interval', $form_state->getValue('hosting_web_cluster_health_check_interval'))
      ->set('health_check_timeout', $form_state->getValue('hosting_web_cluster_health_check_timeout'))
      ->set('sync_method', $form_state->getValue('hosting_web_cluster_sync_method'))
      ->set('auto_failover', $form_state->getValue('hosting_web_cluster_auto_failover'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
