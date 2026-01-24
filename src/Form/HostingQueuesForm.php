<?php

namespace Drupal\hosting\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class HostingQueuesForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'hosting_queues_form';
  }

  protected function getEditableConfigNames(): array {
    return ['hosting.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('hosting.settings');
    $queues_config = $config->get('queues') ?? [];
    $queues = \Drupal::moduleHandler()->invokeAll('hosting_queue_info');
    foreach ($queues_config as $queue_id => $queue_config) {
      $queues[$queue_id] = ($queues[$queue_id] ?? []) + $queue_config;
    }

    $form['queues'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Queues'),
      '#tree' => TRUE,
    ];

    $type_options = [
      'serial' => $this->t('Serial'),
      'batch' => $this->t('Batch'),
      'spread' => $this->t('Spread'),
    ];

    foreach ($queues as $queue_id => $queue) {
      $form['queues'][$queue_id] = [
        '#type' => 'details',
        '#title' => $queue['label'] ?? $queue_id,
        '#open' => TRUE,
      ];
      $form['queues'][$queue_id]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#default_value' => (bool) ($queue['enabled'] ?? TRUE),
      ];
      $form['queues'][$queue_id]['type'] = [
        '#type' => 'select',
        '#title' => $this->t('Queue type'),
        '#options' => $type_options,
        '#default_value' => $queue['type'] ?? 'serial',
      ];
      $form['queues'][$queue_id]['frequency'] = [
        '#type' => 'number',
        '#title' => $this->t('Frequency (seconds)'),
        '#min' => 60,
        '#default_value' => (int) ($queue['frequency'] ?? 300),
      ];
      $form['queues'][$queue_id]['items'] = [
        '#type' => 'number',
        '#title' => $this->t('Items per run (serial only)'),
        '#min' => 0,
        '#default_value' => (int) ($queue['items'] ?? 5),
      ];
      $form['queues'][$queue_id]['threshold'] = [
        '#type' => 'number',
        '#title' => $this->t('Batch threshold'),
        '#min' => 1,
        '#default_value' => (int) ($queue['threshold'] ?? 100),
        '#states' => [
          'visible' => [
            ":input[name='queues[$queue_id][type]']" => ['value' => 'batch'],
          ],
        ],
      ];
      $form['queues'][$queue_id]['min_threads'] = [
        '#type' => 'number',
        '#title' => $this->t('Batch min threads'),
        '#min' => 1,
        '#default_value' => (int) ($queue['min_threads'] ?? 1),
        '#states' => [
          'visible' => [
            ":input[name='queues[$queue_id][type]']" => ['value' => 'batch'],
          ],
        ],
      ];
      $form['queues'][$queue_id]['max_threads'] = [
        '#type' => 'number',
        '#title' => $this->t('Batch max threads'),
        '#min' => 1,
        '#default_value' => (int) ($queue['max_threads'] ?? 6),
        '#states' => [
          'visible' => [
            ":input[name='queues[$queue_id][type]']" => ['value' => 'batch'],
          ],
        ],
      ];
      $form['queues'][$queue_id]['total_items'] = [
        '#type' => 'number',
        '#title' => $this->t('Total items (optional override)'),
        '#min' => 0,
        '#default_value' => isset($queue['total_items']) ? (int) $queue['total_items'] : NULL,
        '#description' => $this->t('Leave empty to use the live queue size when available.'),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $queues = $form_state->getValue('queues') ?? [];
    $config = $this->configFactory->getEditable('hosting.settings');
    $current = $config->get('queues') ?? [];

    foreach ($queues as $queue_id => $values) {
      if (!isset($current[$queue_id])) {
        $current[$queue_id] = [];
      }
      $current[$queue_id]['enabled'] = (bool) $values['enabled'];
      $current[$queue_id]['type'] = $values['type'];
      $current[$queue_id]['frequency'] = (int) $values['frequency'];
      $current[$queue_id]['items'] = (int) $values['items'];
      $current[$queue_id]['threshold'] = (int) $values['threshold'];
      $current[$queue_id]['min_threads'] = (int) $values['min_threads'];
      $current[$queue_id]['max_threads'] = (int) $values['max_threads'];
      if ($values['total_items'] === '' || $values['total_items'] === NULL) {
        unset($current[$queue_id]['total_items']);
      }
      else {
        $current[$queue_id]['total_items'] = (int) $values['total_items'];
      }
    }

    $config->set('queues', $current)->save();
    parent::submitForm($form, $form_state);
  }

}
