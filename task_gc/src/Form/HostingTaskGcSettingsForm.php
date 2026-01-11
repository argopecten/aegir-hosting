<?php

namespace Drupal\hosting_task_gc\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Hosting Task Garbage Collection settings.
 */
class HostingTaskGcSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hosting_task_gc_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['hosting_task_gc.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('hosting_task_gc.settings');

    $form['hosting_task_gc_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable task garbage collection'),
      '#description' => $this->t('Automatically clean up old completed tasks.'),
      '#default_value' => $config->get('enabled') ?? TRUE,
    ];

    $form['hosting_task_gc_frequency'] = [
      '#type' => 'select',
      '#title' => $this->t('Collection frequency'),
      '#description' => $this->t('How often to run the task garbage collection.'),
      '#options' => [
        '3600' => $this->t('Hourly'),
        '21600' => $this->t('Every 6 hours'),
        '43200' => $this->t('Every 12 hours'),
        '86400' => $this->t('Daily'),
        '604800' => $this->t('Weekly'),
      ],
      '#default_value' => $config->get('frequency') ?? '86400',
      '#states' => [
        'visible' => [
          ':input[name="hosting_task_gc_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_task_gc_retention'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Retention settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="hosting_task_gc_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_task_gc_retention']['hosting_task_gc_retention_success'] = [
      '#type' => 'number',
      '#title' => $this->t('Successful tasks retention (days)'),
      '#description' => $this->t('Number of days to keep successfully completed tasks.'),
      '#default_value' => $config->get('retention_success') ?? 30,
      '#min' => 1,
    ];

    $form['hosting_task_gc_retention']['hosting_task_gc_retention_failed'] = [
      '#type' => 'number',
      '#title' => $this->t('Failed tasks retention (days)'),
      '#description' => $this->t('Number of days to keep failed tasks.'),
      '#default_value' => $config->get('retention_failed') ?? 90,
      '#min' => 1,
    ];

    $form['hosting_task_gc_retention']['hosting_task_gc_retention_cancelled'] = [
      '#type' => 'number',
      '#title' => $this->t('Cancelled tasks retention (days)'),
      '#description' => $this->t('Number of days to keep cancelled tasks.'),
      '#default_value' => $config->get('retention_cancelled') ?? 7,
      '#min' => 1,
    ];

    $form['hosting_task_gc_task_types'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Task type specific settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="hosting_task_gc_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_task_gc_task_types']['hosting_task_gc_clean_verify_tasks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clean verify tasks'),
      '#description' => $this->t('Include verify tasks in garbage collection.'),
      '#default_value' => $config->get('clean_verify_tasks') ?? TRUE,
    ];

    $form['hosting_task_gc_task_types']['hosting_task_gc_clean_backup_tasks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clean backup tasks'),
      '#description' => $this->t('Include backup tasks in garbage collection (backup files will be preserved).'),
      '#default_value' => $config->get('clean_backup_tasks') ?? TRUE,
    ];

    $form['hosting_task_gc_task_types']['hosting_task_gc_clean_migrate_tasks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clean migrate tasks'),
      '#description' => $this->t('Include migrate tasks in garbage collection.'),
      '#default_value' => $config->get('clean_migrate_tasks') ?? TRUE,
    ];

    $form['hosting_task_gc_batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Batch size'),
      '#description' => $this->t('Number of tasks to delete in each batch during collection.'),
      '#default_value' => $config->get('batch_size') ?? 100,
      '#min' => 10,
      '#states' => [
        'visible' => [
          ':input[name="hosting_task_gc_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_task_gc_preserve_latest'] = [
      '#type' => 'number',
      '#title' => $this->t('Preserve latest tasks per site'),
      '#description' => $this->t('Always keep this many of the most recent tasks for each site, regardless of age.'),
      '#default_value' => $config->get('preserve_latest') ?? 10,
      '#min' => 0,
      '#states' => [
        'visible' => [
          ':input[name="hosting_task_gc_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_task_gc_log_deletions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log task deletions'),
      '#description' => $this->t('Create log entries when tasks are deleted by garbage collection.'),
      '#default_value' => $config->get('log_deletions') ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="hosting_task_gc_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('hosting_task_gc.settings')
      ->set('enabled', $form_state->getValue('hosting_task_gc_enabled'))
      ->set('frequency', $form_state->getValue('hosting_task_gc_frequency'))
      ->set('retention_success', $form_state->getValue('hosting_task_gc_retention_success'))
      ->set('retention_failed', $form_state->getValue('hosting_task_gc_retention_failed'))
      ->set('retention_cancelled', $form_state->getValue('hosting_task_gc_retention_cancelled'))
      ->set('clean_verify_tasks', $form_state->getValue('hosting_task_gc_clean_verify_tasks'))
      ->set('clean_backup_tasks', $form_state->getValue('hosting_task_gc_clean_backup_tasks'))
      ->set('clean_migrate_tasks', $form_state->getValue('hosting_task_gc_clean_migrate_tasks'))
      ->set('batch_size', $form_state->getValue('hosting_task_gc_batch_size'))
      ->set('preserve_latest', $form_state->getValue('hosting_task_gc_preserve_latest'))
      ->set('log_deletions', $form_state->getValue('hosting_task_gc_log_deletions'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
