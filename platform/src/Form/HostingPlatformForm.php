<?php

namespace Drupal\hosting_platform\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Hosting platform add/edit forms.
 */
class HostingPlatformForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    
    // Add directory name field for composer deployment
    $base_path = \Drupal::config('hosting_platform.settings')->get('hosting_platform_base_path') ?? '/var/aegir/platforms/';
    $form['directory_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Directory name'),
      '#description' => $this->t('The directory name for the platform. Will be created under @base', ['@base' => $base_path]),
      '#maxlength' => 255,
      '#weight' => -8,
      '#states' => [
        'visible' => [
          ':input[name="deployment_method"]' => ['value' => 'composer'],
        ],
      ],
    ];
    
    // Add conditional field display based on deployment method
    if (isset($form['repository_url'])) {
      $form['repository_url']['#states'] = [
        'visible' => [
          ':input[name="deployment_method"]' => ['value' => 'composer'],
        ],
      ];
    }
    
    if (isset($form['publish_path'])) {
      $form['publish_path']['#states'] = [
        'visible' => [
          ':input[name="deployment_method"]' => ['value' => 'manual'],
        ],
      ];
    }
    
    // Add JavaScript to auto-populate directory name from label
    $form['#attached']['library'][] = 'hosting_platform/platform-form';
    
    if (function_exists('hosting_platform_apply_form_overrides')) {
      hosting_platform_apply_form_overrides($form, $form_state, $this->entity);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    
    $deployment_method = $form_state->getValue('deployment_method');
    $deployment_method = is_array($deployment_method) ? $deployment_method[0]['value'] : $deployment_method;
    
    if ($deployment_method === 'manual') {
      $this->validateManualDeployment($form, $form_state);
    }
    elseif ($deployment_method === 'composer') {
      $this->validateComposerDeployment($form, $form_state);
    }
  }

  /**
   * Validates manual deployment method.
   */
  protected function validateManualDeployment(array &$form, FormStateInterface $form_state) {
    $publish_path_field = $form_state->getValue('publish_path');
    $publish_path = is_array($publish_path_field) ? $publish_path_field[0]['value'] : $publish_path_field;
    
    if (empty($publish_path)) {
      $form_state->setErrorByName('publish_path', $this->t('Publish path is required for manual deployment.'));
      return;
    }
    
    // Check if path exists
    if (!is_dir($publish_path)) {
      $form_state->setErrorByName('publish_path', $this->t('The specified path does not exist: @path', ['@path' => $publish_path]));
      return;
    }
    
    // Check for valid Drupal platform - support both root and web subdirectory structures
    $paths_to_check = [
      $publish_path,                    // Traditional structure or web root
      $publish_path . '/web',          // Composer-based structure with web subdirectory
    ];
    
    $valid_platform = FALSE;
    foreach ($paths_to_check as $check_path) {
      $index_php = $check_path . '/index.php';
      $autoload = $check_path . '/autoload.php';
      $vendor_autoload = $check_path . '/vendor/autoload.php';
      
      if (file_exists($index_php) && (file_exists($autoload) || file_exists($vendor_autoload))) {
        $valid_platform = TRUE;
        break;
      }
    }
    
    if (!$valid_platform) {
      $form_state->setErrorByName('publish_path', $this->t('This does not appear to be a valid Drupal platform. Missing index.php or autoload.php file.'));
      return;
    }
  }

  /**
   * Validates composer deployment method.
   */
  protected function validateComposerDeployment(array &$form, FormStateInterface $form_state) {
    $repository_url_field = $form_state->getValue('repository_url');
    $repository_url = is_array($repository_url_field) ? $repository_url_field[0]['value'] : $repository_url_field;
    
    if (empty($repository_url)) {
      $form_state->setErrorByName('repository_url', $this->t('Repository URL is required for Composer deployment.'));
      return;
    }
    
    // Validate directory name
    $dir_name = $form_state->getValue('directory_name');
    if (!empty($dir_name)) {
      // Check for invalid characters
      if (!preg_match('/^[a-z0-9_-]+$/i', $dir_name)) {
        $form_state->setErrorByName('directory_name', $this->t('Directory name can only contain letters, numbers, hyphens, and underscores.'));
        return;
      }
      
      // Check if directory already exists
      $base_path = \Drupal::config('hosting_platform.settings')->get('hosting_platform_base_path') ?? '/var/aegir/platforms/';
      $target_path = rtrim($base_path, '/') . '/' . $dir_name;
      if (file_exists($target_path)) {
        $form_state->setErrorByName('directory_name', $this->t('A directory with this name already exists: @path', ['@path' => $target_path]));
        return;
      }
    }
    
    // Check if composer is available
    $composer_path = exec('which composer 2>/dev/null');
    if (empty($composer_path)) {
      $form_state->setErrorByName('repository_url', $this->t('Composer is not available on the system.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $deployment_method = $form_state->getValue('deployment_method');
    $deployment_method = is_array($deployment_method) ? $deployment_method[0]['value'] : $deployment_method;
    
    // Handle composer deployment
    if ($deployment_method === 'composer' && $this->entity->isNew()) {
      $result = $this->executeComposerDeploy($form, $form_state);
      if (!$result['success']) {
        $this->messenger()->addError($this->t('Composer deployment failed: @error', ['@error' => $result['error']]));
        return;
      }
      
      // Set the publish_path to the created directory
      $this->entity->set('publish_path', $result['path']);
    }
    
    $status = parent::save($form, $form_state);
    $this->messenger()->addStatus($this->t('Saved %label.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.hosting_platform.collection');
    return $status;
  }

  /**
   * Executes composer create-project.
   */
  protected function executeComposerDeploy(array &$form, FormStateInterface $form_state) {
    $repository_url_field = $form_state->getValue('repository_url');
    $repository_url = is_array($repository_url_field) ? $repository_url_field[0]['value'] : $repository_url_field;
    
    // Get directory name from form or generate from label
    $dir_name = $form_state->getValue('directory_name');
    if (empty($dir_name)) {
      $label_field = $form_state->getValue('label');
      $label = is_array($label_field) ? $label_field[0]['value'] : $label_field;
      $dir_name = $this->sanitizeDirectoryName($label);
    }
    else {
      // Sanitize user-provided directory name
      $dir_name = $this->sanitizeDirectoryName($dir_name);
    }
    
    $base_path = \Drupal::config('hosting_platform.settings')->get('hosting_platform_base_path') ?? '/var/aegir/platforms/';
    $target_path = rtrim($base_path, '/') . '/' . $dir_name;
    
    // Check if path already exists
    if (file_exists($target_path)) {
      return [
        'success' => FALSE,
        'error' => $this->t('Target directory already exists: @path', ['@path' => $target_path]),
      ];
    }
    
    // Execute composer create-project as aegir user
    // Set COMPOSER_HOME to avoid cache permission issues
    $composer_cmd = sprintf(
      'sudo -u aegir COMPOSER_HOME=/var/aegir/.composer HOME=/var/aegir composer create-project %s %s --no-interaction 2>&1',
      escapeshellarg($repository_url),
      escapeshellarg($target_path)
    );
    
    exec($composer_cmd, $output, $return_code);
    
    if ($return_code !== 0) {
      return [
        'success' => FALSE,
        'error' => implode("\n", $output),
      ];
    }
    
    return [
      'success' => TRUE,
      'path' => $target_path,
    ];
  }

  /**
   * Sanitizes a string to be used as a directory name.
   *
   * @param string $name
   *   The name to sanitize.
   *
   * @return string
   *   The sanitized directory name.
   */
  protected function sanitizeDirectoryName($name) {
    // Convert to lowercase and replace non-alphanumeric characters with underscores
    $dir_name = preg_replace('/[^a-z0-9_-]/i', '_', strtolower($name));
    // Replace multiple consecutive underscores with a single one
    $dir_name = preg_replace('/_+/', '_', $dir_name);
    // Remove leading/trailing underscores
    $dir_name = trim($dir_name, '_');
    // Ensure it's not empty
    if (empty($dir_name)) {
      $dir_name = 'platform_' . time();
    }
    return $dir_name;
  }

}
