<?php

namespace Drupal\hosting_subdirs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Hosting Subdirectories settings.
 */
class HostingSubdirsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hosting_subdirs_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['hosting_subdirs.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('hosting_subdirs.settings');

    $form['hosting_subdirs_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable subdirectories support'),
      '#description' => $this->t('Allow sites to be created in subdirectories of a domain.'),
      '#default_value' => $config->get('enabled') ?? FALSE,
    ];

    $form['hosting_subdirs_max_depth'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum subdirectory depth'),
      '#description' => $this->t('The maximum depth of subdirectories allowed. For example, 2 would allow example.com/subdir/subdir2 but not deeper.'),
      '#default_value' => $config->get('max_depth') ?? 3,
      '#min' => 1,
      '#max' => 10,
      '#states' => [
        'visible' => [
          ':input[name="hosting_subdirs_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_subdirs_allowed_chars'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed characters'),
      '#description' => $this->t('Regular expression pattern for allowed characters in subdirectory names.'),
      '#default_value' => $config->get('allowed_chars') ?? '[a-z0-9\-_]',
      '#maxlength' => 255,
      '#states' => [
        'visible' => [
          ':input[name="hosting_subdirs_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_subdirs_validate_uniqueness'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate subdirectory uniqueness'),
      '#description' => $this->t('Ensure that subdirectory paths are unique across all sites.'),
      '#default_value' => $config->get('validate_uniqueness') ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="hosting_subdirs_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_subdirs_reserved_names'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Reserved subdirectory names'),
      '#description' => $this->t('Enter one reserved name per line. These names cannot be used for subdirectories.'),
      '#default_value' => $config->get('reserved_names') ?? "admin\napi\napp\nwp-admin\nwp-content",
      '#rows' => 10,
      '#states' => [
        'visible' => [
          ':input[name="hosting_subdirs_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate regex pattern.
    $pattern = $form_state->getValue('hosting_subdirs_allowed_chars');
    if ($pattern && @preg_match('/' . $pattern . '/', '') === FALSE) {
      $form_state->setErrorByName('hosting_subdirs_allowed_chars', $this->t('Invalid regular expression pattern.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('hosting_subdirs.settings')
      ->set('enabled', $form_state->getValue('hosting_subdirs_enabled'))
      ->set('max_depth', $form_state->getValue('hosting_subdirs_max_depth'))
      ->set('allowed_chars', $form_state->getValue('hosting_subdirs_allowed_chars'))
      ->set('validate_uniqueness', $form_state->getValue('hosting_subdirs_validate_uniqueness'))
      ->set('reserved_names', $form_state->getValue('hosting_subdirs_reserved_names'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
