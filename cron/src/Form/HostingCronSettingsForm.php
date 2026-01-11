<?php

namespace Drupal\hosting_cron\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Hosting Cron settings.
 */
class HostingCronSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hosting_cron_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['hosting_cron.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('hosting_cron.settings');

    $form['hosting_cron_default_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Default cron interval'),
      '#description' => $this->t('Select the default interval for running cron tasks on sites.'),
      '#options' => [
        '3600' => $this->t('Hourly'),
        '10800' => $this->t('Every 3 hours'),
        '21600' => $this->t('Every 6 hours'),
        '43200' => $this->t('Every 12 hours'),
        '86400' => $this->t('Daily'),
        '172800' => $this->t('Every 2 days'),
        '604800' => $this->t('Weekly'),
      ],
      '#default_value' => $config->get('default_interval') ?? '86400',
    ];

    $form['hosting_cron_use_backend'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use backend cron'),
      '#description' => $this->t('If enabled, cron tasks will be executed via the backend provision system.'),
      '#default_value' => $config->get('use_backend') ?? TRUE,
    ];

    $form['hosting_cron_queue_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable cron queue'),
      '#description' => $this->t('Enable the cron queue for processing cron tasks.'),
      '#default_value' => $config->get('queue_enabled') ?? TRUE,
    ];

    $form['hosting_cron_queue_frequency'] = [
      '#type' => 'number',
      '#title' => $this->t('Queue frequency'),
      '#description' => $this->t('The frequency in seconds for processing the cron queue.'),
      '#default_value' => $config->get('queue_frequency') ?? 300,
      '#min' => 60,
      '#states' => [
        'visible' => [
          ':input[name="hosting_cron_queue_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_cron_queue_items'] = [
      '#type' => 'number',
      '#title' => $this->t('Queue items per run'),
      '#description' => $this->t('The number of items to process in each queue run.'),
      '#default_value' => $config->get('queue_items') ?? 5,
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':input[name="hosting_cron_queue_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('hosting_cron.settings')
      ->set('default_interval', $form_state->getValue('hosting_cron_default_interval'))
      ->set('use_backend', $form_state->getValue('hosting_cron_use_backend'))
      ->set('queue_enabled', $form_state->getValue('hosting_cron_queue_enabled'))
      ->set('queue_frequency', $form_state->getValue('hosting_cron_queue_frequency'))
      ->set('queue_items', $form_state->getValue('hosting_cron_queue_items'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
