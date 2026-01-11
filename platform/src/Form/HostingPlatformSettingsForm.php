<?php

namespace Drupal\hosting_platform\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Hosting Platform settings.
 */
class HostingPlatformSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hosting_platform_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['hosting_platform.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('hosting_platform.settings');

    $form['paths'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Platform paths'),
      '#collapsible' => FALSE,
    ];

    $form['paths']['hosting_platform_base_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Platform base path'),
      '#description' => $this->t('The base directory where platforms will be stored.'),
      '#default_value' => $config->get('hosting_platform_base_path') ?? '/var/aegir/platforms/',
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['paths']['hosting_platform_composer_cache_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Composer cache directory'),
      '#description' => $this->t('Directory for Composer cache to speed up deployments.'),
      '#default_value' => $config->get('hosting_platform_composer_cache_dir') ?? '/var/aegir/.composer/cache',
      '#maxlength' => 255,
    ];

    $form['deployment'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Deployment settings'),
      '#collapsible' => FALSE,
    ];

    $form['deployment']['hosting_platform_default_deployment_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Default deployment method'),
      '#description' => $this->t('The default method for deploying new platforms.'),
      '#options' => [
        'manual' => $this->t('Manual (existing directory)'),
        'composer' => $this->t('Composer'),
      ],
      '#default_value' => $config->get('hosting_platform_default_deployment_method') ?? 'manual',
    ];

    $form['deployment']['hosting_platform_composer_memory_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Composer memory limit'),
      '#description' => $this->t('Memory limit for Composer operations (e.g., 2G, 512M). Leave empty for no limit.'),
      '#default_value' => $config->get('hosting_platform_composer_memory_limit') ?? '2G',
      '#maxlength' => 10,
    ];

    $form['deployment']['hosting_platform_composer_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Composer timeout'),
      '#description' => $this->t('Timeout in seconds for Composer operations.'),
      '#default_value' => $config->get('hosting_platform_composer_timeout') ?? 3600,
      '#min' => 60,
    ];

    $form['deployment']['hosting_platform_git_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Git clone timeout'),
      '#description' => $this->t('Timeout in seconds for Git clone operations.'),
      '#default_value' => $config->get('hosting_platform_git_timeout') ?? 1800,
      '#min' => 60,
    ];

    $form['validation'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Validation settings'),
      '#collapsible' => FALSE,
    ];

    $form['validation']['hosting_platform_validate_drupal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate Drupal installation'),
      '#description' => $this->t('Verify that the platform contains a valid Drupal installation.'),
      '#default_value' => $config->get('hosting_platform_validate_drupal') ?? TRUE,
    ];

    $form['validation']['hosting_platform_require_composer_json'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require composer.json'),
      '#description' => $this->t('Require platforms to have a composer.json file.'),
      '#default_value' => $config->get('hosting_platform_require_composer_json') ?? FALSE,
    ];

    $form['validation']['hosting_platform_check_disk_space'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check disk space'),
      '#description' => $this->t('Verify sufficient disk space before creating platforms.'),
      '#default_value' => $config->get('hosting_platform_check_disk_space') ?? TRUE,
    ];

    $form['validation']['hosting_platform_min_disk_space'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum disk space (MB)'),
      '#description' => $this->t('Minimum free disk space in megabytes required for platform creation.'),
      '#default_value' => $config->get('hosting_platform_min_disk_space') ?? 500,
      '#min' => 100,
      '#states' => [
        'visible' => [
          ':input[name="hosting_platform_check_disk_space"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['locking'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Platform locking'),
      '#collapsible' => FALSE,
    ];

    $form['locking']['hosting_platform_lock_by_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Lock platforms by default'),
      '#description' => $this->t('Lock newly created platforms to prevent site creation until verified.'),
      '#default_value' => $config->get('hosting_platform_lock_by_default') ?? FALSE,
    ];

    $form['locking']['hosting_platform_auto_unlock_after_verify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-unlock after verification'),
      '#description' => $this->t('Automatically unlock platforms after successful verification.'),
      '#default_value' => $config->get('hosting_platform_auto_unlock_after_verify') ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="hosting_platform_lock_by_default"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['site_import'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Site import settings'),
      '#collapsible' => FALSE,
    ];

    $form['site_import']['hosting_platform_automatic_site_import'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically import sites'),
      '#description' => $this->t('Automatically import existing sites when verifying a platform.'),
      '#default_value' => $config->get('hosting_platform_automatic_site_import') ?? TRUE,
    ];

    $form['site_import']['hosting_platform_import_ignore_patterns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Import ignore patterns'),
      '#description' => $this->t('Site directory patterns to ignore during import (one per line). Supports wildcards.'),
      '#default_value' => $config->get('hosting_platform_import_ignore_patterns') ?? "default\nexample.com\ntemplate",
      '#rows' => 5,
      '#states' => [
        'visible' => [
          ':input[name="hosting_platform_automatic_site_import"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['maintenance'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Maintenance settings'),
      '#collapsible' => FALSE,
    ];

    $form['maintenance']['hosting_platform_auto_verify_frequency'] = [
      '#type' => 'select',
      '#title' => $this->t('Auto-verify frequency'),
      '#description' => $this->t('How often to automatically verify platforms.'),
      '#options' => [
        '0' => $this->t('Disabled'),
        '3600' => $this->t('Hourly'),
        '21600' => $this->t('Every 6 hours'),
        '43200' => $this->t('Every 12 hours'),
        '86400' => $this->t('Daily'),
        '604800' => $this->t('Weekly'),
      ],
      '#default_value' => $config->get('hosting_platform_auto_verify_frequency') ?? '0',
    ];

    $form['maintenance']['hosting_platform_cleanup_old_releases'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cleanup old releases'),
      '#description' => $this->t('Automatically remove old Composer-deployed platform releases.'),
      '#default_value' => $config->get('hosting_platform_cleanup_old_releases') ?? FALSE,
    ];

    $form['maintenance']['hosting_platform_keep_releases'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of releases to keep'),
      '#description' => $this->t('How many old releases to keep before cleanup.'),
      '#default_value' => $config->get('hosting_platform_keep_releases') ?? 3,
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':input[name="hosting_platform_cleanup_old_releases"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['permissions'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('File permissions'),
      '#collapsible' => FALSE,
    ];

    $form['permissions']['hosting_platform_default_dir_mode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default directory permissions'),
      '#description' => $this->t('Default file permissions for platform directories (octal, e.g., 0755).'),
      '#default_value' => $config->get('hosting_platform_default_dir_mode') ?? '0755',
      '#maxlength' => 4,
    ];

    $form['permissions']['hosting_platform_default_file_mode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default file permissions'),
      '#description' => $this->t('Default file permissions for platform files (octal, e.g., 0644).'),
      '#default_value' => $config->get('hosting_platform_default_file_mode') ?? '0644',
      '#maxlength' => 4,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate base path
    $base_path = $form_state->getValue('hosting_platform_base_path');
    if (!is_dir($base_path)) {
      $form_state->setErrorByName('hosting_platform_base_path', $this->t('The platform base path does not exist.'));
    }
    elseif (!is_writable($base_path)) {
      $form_state->setErrorByName('hosting_platform_base_path', $this->t('The platform base path is not writable.'));
    }

    // Validate file permissions format
    $dir_mode = $form_state->getValue('hosting_platform_default_dir_mode');
    if (!preg_match('/^0[0-7]{3}$/', $dir_mode)) {
      $form_state->setErrorByName('hosting_platform_default_dir_mode', $this->t('Directory permissions must be in octal format (e.g., 0755).'));
    }

    $file_mode = $form_state->getValue('hosting_platform_default_file_mode');
    if (!preg_match('/^0[0-7]{3}$/', $file_mode)) {
      $form_state->setErrorByName('hosting_platform_default_file_mode', $this->t('File permissions must be in octal format (e.g., 0644).'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('hosting_platform.settings')
      ->set('hosting_platform_base_path', rtrim($form_state->getValue('hosting_platform_base_path'), '/') . '/')
      ->set('hosting_platform_composer_cache_dir', $form_state->getValue('hosting_platform_composer_cache_dir'))
      ->set('hosting_platform_default_deployment_method', $form_state->getValue('hosting_platform_default_deployment_method'))
      ->set('hosting_platform_composer_memory_limit', $form_state->getValue('hosting_platform_composer_memory_limit'))
      ->set('hosting_platform_composer_timeout', $form_state->getValue('hosting_platform_composer_timeout'))
      ->set('hosting_platform_git_timeout', $form_state->getValue('hosting_platform_git_timeout'))
      ->set('hosting_platform_validate_drupal', $form_state->getValue('hosting_platform_validate_drupal'))
      ->set('hosting_platform_require_composer_json', $form_state->getValue('hosting_platform_require_composer_json'))
      ->set('hosting_platform_check_disk_space', $form_state->getValue('hosting_platform_check_disk_space'))
      ->set('hosting_platform_min_disk_space', $form_state->getValue('hosting_platform_min_disk_space'))
      ->set('hosting_platform_lock_by_default', $form_state->getValue('hosting_platform_lock_by_default'))
      ->set('hosting_platform_auto_unlock_after_verify', $form_state->getValue('hosting_platform_auto_unlock_after_verify'))
      ->set('hosting_platform_automatic_site_import', $form_state->getValue('hosting_platform_automatic_site_import'))
      ->set('hosting_platform_import_ignore_patterns', $form_state->getValue('hosting_platform_import_ignore_patterns'))
      ->set('hosting_platform_auto_verify_frequency', $form_state->getValue('hosting_platform_auto_verify_frequency'))
      ->set('hosting_platform_cleanup_old_releases', $form_state->getValue('hosting_platform_cleanup_old_releases'))
      ->set('hosting_platform_keep_releases', $form_state->getValue('hosting_platform_keep_releases'))
      ->set('hosting_platform_default_dir_mode', $form_state->getValue('hosting_platform_default_dir_mode'))
      ->set('hosting_platform_default_file_mode', $form_state->getValue('hosting_platform_default_file_mode'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
