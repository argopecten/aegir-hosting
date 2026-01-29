<?php

namespace Drupal\hosting_platform\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hosting_platform\Service\PlatformManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HostingPlatformForm extends ContentEntityForm {

  protected PlatformManager $platformManager;

  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, PlatformManager $platform_manager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->platformManager = $platform_manager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('hosting_platform.manager'),
    );
  }

  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    // Add AJAX callback to platform_type field to show/hide fields
    if (isset($form['platform_type'])) {
      $form['platform_type']['widget']['#ajax'] = [
        'callback' => '::platformTypeAjaxCallback',
        'wrapper' => 'platform-config-wrapper',
      ];
    }

    // Wrap platform configuration fields
    $form['platform_config'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'platform-config-wrapper'],
      '#weight' => 0,
    ];

    // Get current platform type
    $platform_type = $form_state->getValue('platform_type');
    if (!$platform_type) {
      $platform_type = $this->entity->get('platform_type')->value ?: 'manual';
    }
    // Handle array format from form state
    if (is_array($platform_type)) {
      $platform_type = $platform_type[0]['value'] ?? 'manual';
    }

    // Move git_url into wrapper
    if (isset($form['git_url'])) {
      $form['platform_config']['git_url'] = $form['git_url'];
      unset($form['git_url']);
      $form['platform_config']['git_url']['#access'] = ($platform_type === 'git');
      
      // Ensure it's a single-line textfield
      if (isset($form['platform_config']['git_url']['widget'][0]['value'])) {
        $form['platform_config']['git_url']['widget'][0]['value']['#type'] = 'textfield';
        $form['platform_config']['git_url']['widget'][0]['value']['#rows'] = NULL;
      }
    }

    // Move publish_path into wrapper
    if (isset($form['publish_path'])) {
      $form['platform_config']['publish_path'] = $form['publish_path'];
      unset($form['publish_path']);
      
      // Ensure it's a single-line textfield
      if (isset($form['platform_config']['publish_path']['widget'][0]['value'])) {
        $form['platform_config']['publish_path']['widget'][0]['value']['#type'] = 'textfield';
        $form['platform_config']['publish_path']['widget'][0]['value']['#rows'] = NULL;
      }
      
      if ($platform_type === 'git') {
        $form['platform_config']['publish_path']['#access'] = FALSE;
        $form['platform_config']['publish_path']['widget'][0]['value']['#required'] = FALSE;
      }
    }

    // Handle web server field - auto-populate if only one server, show radio buttons otherwise
    if (isset($form['web_server'])) {
      $web_servers = $this->getWebServers();
      
      if (count($web_servers) === 1) {
        // Only one web server - hide field and auto-populate
        $form['web_server']['#access'] = FALSE;
        $server_ids = array_keys($web_servers);
        $server_id = reset($server_ids);
        
        // Load the entity object for default value
        $server = $this->entityTypeManager->getStorage('hosting_server')->load($server_id);
        if ($server) {
          $form['web_server']['widget'][0]['target_id']['#default_value'] = $server;
        }
      }
      elseif (count($web_servers) > 1) {
        // Multiple web servers - show as radio buttons
        $form['web_server']['widget'][0]['target_id']['#type'] = 'radios';
        $form['web_server']['widget'][0]['target_id']['#options'] = $web_servers;
        unset($form['web_server']['widget'][0]['target_id']['#autocomplete_route_name']);
        unset($form['web_server']['widget'][0]['target_id']['#autocomplete_route_parameters']);
      }
    }

    return $form;
  }

  /**
   * Get available web servers with HTTP service.
   *
   * @return array
   *   Array of server IDs => labels for servers with HTTP service.
   */
  protected function getWebServers(): array {
    $web_servers = [];
    
    $server_storage = $this->entityTypeManager->getStorage('hosting_server');
    $service_storage = $this->entityTypeManager->getStorage('hosting_service_instance');
    
    // Get all enabled servers
    $servers = $server_storage->loadByProperties(['status' => 1]);
    
    foreach ($servers as $server) {
      // Check if this server has an HTTP service
      $services = $service_storage->loadByProperties([
        'server' => $server->id(),
        'service_type' => 'http',
        'available' => TRUE,
      ]);
      
      if (!empty($services)) {
        $label = $server->get('human_name')->value ?: $server->get('hostname')->value;
        $web_servers[$server->id()] = $label;
      }
    }
    
    return $web_servers;
  }

  /**
   * AJAX callback for platform type changes.
   */
  public function platformTypeAjaxCallback(array &$form, FormStateInterface $form_state): array {
    return $form['platform_config'];
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $platform_type = $this->getFieldValue($form_state->getValue('platform_type'));
    $git_url = $this->getFieldValue($form_state->getValue('git_url'));
    $publish_path = $this->getFieldValue($form_state->getValue('publish_path'));

    if ($platform_type === 'git') {
      // Validate git URL
      if (!$git_url) {
        $form_state->setErrorByName('git_url', $this->t('Git repository URL is required for git-based platforms.'));
      }
      elseif (!$this->platformManager->isValidGitUrl($git_url)) {
        $form_state->setErrorByName('git_url', $this->t('Please provide a valid GitHub, GitLab, or Bitbucket repository URL.'));
      }
    }
    else {
      // Validate publish path for manual platforms
      if ($publish_path && !$this->platformManager->isValidPublishPath($publish_path)) {
        $form_state->setErrorByName('publish_path', $this->t('Publish path must be an absolute path within @base_dir to an existing directory.', [
          '@base_dir' => \Drupal\hosting_platform\Service\PlatformManager::PLATFORMS_BASE_DIR,
        ]));
      }

      if ($publish_path && !$this->platformManager->hasValidComposerJson($publish_path)) {
        $form_state->setErrorByName('publish_path', $this->t('The platform directory must contain a valid composer.json file. Only Composer-based Drupal projects are supported.'));
      }

      $entity_id = $this->entity->id() ? (int) $this->entity->id() : NULL;
      if ($publish_path && !$this->platformManager->isPublishPathUnique($publish_path, $entity_id)) {
        $form_state->setErrorByName('publish_path', $this->t('Publish path must be unique.'));
      }
    }
  }

  public function save(array $form, FormStateInterface $form_state): int {
    $platform_type = $this->getFieldValue($form_state->getValue('platform_type'));
    
    // Handle git-based platform
    if ($platform_type === 'git' && $this->entity->isNew()) {
      $git_url = $this->getFieldValue($form_state->getValue('git_url'));
      $target_path = $this->platformManager->generatePlatformPathFromGitUrl($git_url);
      
      // Clone the repository
      $result = $this->platformManager->cloneGitRepository($git_url, $target_path);
      
      if (!$result['success']) {
        $this->messenger()->addError($this->t('Failed to clone repository: @message', [
          '@message' => $result['message'],
        ]));
        return SAVED_NEW;
      }
      
      // Set the publish path
      $this->entity->set('publish_path', $target_path);
      $this->messenger()->addStatus($this->t('Repository cloned successfully to @path', [
        '@path' => $target_path,
      ]));
    }

    if ($this->entity->isNew() && !$this->entity->get('status')->value) {
      $this->entity->set('status', $this->platformManager->getDefaultStatus());
    }

    return parent::save($form, $form_state);
  }

  protected function getFieldValue($value): string {
    if (is_array($value)) {
      $first = reset($value);
      if (is_array($first) && array_key_exists('value', $first)) {
        return (string) $first['value'];
      }
    }
    return is_scalar($value) ? (string) $value : '';
  }

}
