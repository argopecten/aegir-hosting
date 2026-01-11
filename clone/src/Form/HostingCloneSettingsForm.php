<?php

namespace Drupal\hosting_clone\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Hosting Clone settings.
 */
class HostingCloneSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hosting_clone_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['hosting_clone.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('hosting_clone.settings');

    $form['hosting_clone_uri_validation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate clone URI'),
      '#description' => $this->t('Ensure that the new URI for cloned sites is valid and unique.'),
      '#default_value' => $config->get('uri_validation') ?? TRUE,
    ];

    $form['hosting_clone_copy_database'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Copy database by default'),
      '#description' => $this->t('Copy the database content when cloning sites by default.'),
      '#default_value' => $config->get('copy_database') ?? TRUE,
    ];

    $form['hosting_clone_copy_files'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Copy files by default'),
      '#description' => $this->t('Copy the files directory when cloning sites by default.'),
      '#default_value' => $config->get('copy_files') ?? TRUE,
    ];

    $form['hosting_clone_copy_private_files'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Copy private files'),
      '#description' => $this->t('Include private files directory when copying files.'),
      '#default_value' => $config->get('copy_private_files') ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="hosting_clone_copy_files"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_clone_preserve_client'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preserve client ownership'),
      '#description' => $this->t('Keep the same client for cloned sites.'),
      '#default_value' => $config->get('preserve_client') ?? TRUE,
    ];

    $form['hosting_clone_preserve_platform'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preserve platform'),
      '#description' => $this->t('Clone sites on the same platform by default.'),
      '#default_value' => $config->get('preserve_platform') ?? TRUE,
    ];

    $form['hosting_clone_naming_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default naming pattern'),
      '#description' => $this->t('Pattern for generating clone names. Use %original for the original site name and %date for the date.'),
      '#default_value' => $config->get('naming_pattern') ?? '%original-clone-%date',
      '#maxlength' => 255,
    ];

    $form['hosting_clone_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Clone operation timeout'),
      '#description' => $this->t('Maximum time in seconds for clone operations.'),
      '#default_value' => $config->get('timeout') ?? 3600,
      '#min' => 60,
    ];

    $form['hosting_clone_concurrent_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Concurrent clone limit'),
      '#description' => $this->t('Maximum number of concurrent clone operations allowed.'),
      '#default_value' => $config->get('concurrent_limit') ?? 3,
      '#min' => 1,
    ];

    $form['hosting_clone_create_backup'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create backup before cloning'),
      '#description' => $this->t('Automatically create a backup of the source site before cloning.'),
      '#default_value' => $config->get('create_backup') ?? FALSE,
    ];

    $form['hosting_clone_notification_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable clone notifications'),
      '#description' => $this->t('Send email notifications when clone operations complete.'),
      '#default_value' => $config->get('notification_enabled') ?? TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('hosting_clone.settings')
      ->set('uri_validation', $form_state->getValue('hosting_clone_uri_validation'))
      ->set('copy_database', $form_state->getValue('hosting_clone_copy_database'))
      ->set('copy_files', $form_state->getValue('hosting_clone_copy_files'))
      ->set('copy_private_files', $form_state->getValue('hosting_clone_copy_private_files'))
      ->set('preserve_client', $form_state->getValue('hosting_clone_preserve_client'))
      ->set('preserve_platform', $form_state->getValue('hosting_clone_preserve_platform'))
      ->set('naming_pattern', $form_state->getValue('hosting_clone_naming_pattern'))
      ->set('timeout', $form_state->getValue('hosting_clone_timeout'))
      ->set('concurrent_limit', $form_state->getValue('hosting_clone_concurrent_limit'))
      ->set('create_backup', $form_state->getValue('hosting_clone_create_backup'))
      ->set('notification_enabled', $form_state->getValue('hosting_clone_notification_enabled'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
