<?php

namespace Drupal\hosting_server\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\hosting_server\Service\ServiceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HostingServiceInstanceForm extends ContentEntityForm {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected ServiceManager $serviceManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, ServiceManager $service_manager) {
    parent::__construct($entity_type_manager);
    $this->entityTypeManager = $entity_type_manager;
    $this->serviceManager = $service_manager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('hosting.service_manager'),
    );
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $service_type_value = $this->getFormValue($form_state, 'service_type', (string) $this->entity->get('service_type')->value);
    $service_type_options = $this->serviceManager->getServiceTypes();

    if (isset($form['service_type']['widget'][0]['value'])) {
      $form['service_type']['widget'][0]['value']['#type'] = 'select';
      $form['service_type']['widget'][0]['value']['#options'] = $service_type_options;
      $form['service_type']['widget'][0]['value']['#empty_option'] = $this->t('- Select -');
      $form['service_type']['widget'][0]['value']['#default_value'] = $service_type_value;
    }

    $provider_options = $service_type_value ? $this->serviceManager->getProvidersForType($service_type_value) : [];
    $provider_value = $this->getFormValue($form_state, 'provider', (string) $this->entity->get('provider')->value);

    if (isset($form['provider']['widget'][0]['value'])) {
      $form['provider']['widget'][0]['value']['#type'] = 'select';
      $form['provider']['widget'][0]['value']['#options'] = $provider_options;
      $form['provider']['widget'][0]['value']['#empty_option'] = $this->t('- Select -');
      $form['provider']['widget'][0]['value']['#default_value'] = $provider_value;
    }

    if (isset($form['config'])) {
      $form['config']['#access'] = FALSE;
    }
    if (isset($form['port'])) {
      $form['port']['#access'] = FALSE;
    }
    if (isset($form['restart_cmd'])) {
      $form['restart_cmd']['#access'] = FALSE;
    }

    if ($provider_value) {
      $config = $this->decodeConfig($this->entity->get('config')->value);
      $provider = $this->serviceManager->createProvider($provider_value, $config);

      $form['provider_config'] = [
        '#type' => 'details',
        '#title' => $this->t('Provider settings'),
        '#open' => TRUE,
        '#tree' => TRUE,
      ];

      $subform_state = SubformState::createForSubform($form['provider_config'], $form, $form_state);
      $form['provider_config'] = $provider->buildConfigurationForm($form['provider_config'], $subform_state);
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $provider_id = $this->getFormValue($form_state, 'provider', (string) $this->entity->get('provider')->value);
    if ($provider_id) {
      $config = $this->decodeConfig($this->entity->get('config')->value);
      $provider = $this->serviceManager->createProvider($provider_id, $config);
      $subform = $form['provider_config'] ?? [];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      $provider->validateConfigurationForm($subform, $subform_state);
    }
  }

  public function save(array $form, FormStateInterface $form_state): int {
    $this->applyProviderConfiguration($form, $form_state);
    return parent::save($form, $form_state);
  }

  protected function applyProviderConfiguration(array $form, FormStateInterface $form_state): void {
    $provider_id = $this->getFormValue($form_state, 'provider', (string) $this->entity->get('provider')->value);
    if (!$provider_id) {
      return;
    }

    $config = $this->decodeConfig($this->entity->get('config')->value);
    $provider = $this->serviceManager->createProvider($provider_id, $config);
    $subform = $form['provider_config'] ?? [];
    $subform_state = SubformState::createForSubform($subform, $form, $form_state);
    $provider->submitConfigurationForm($subform, $subform_state);
    $config = $provider->getConfiguration();

    $this->entity->set('config', $this->encodeConfig($config));
    if (array_key_exists('port', $config)) {
      $this->entity->set('port', (int) $config['port']);
    }
    if (array_key_exists('restart_cmd', $config)) {
      $this->entity->set('restart_cmd', (string) $config['restart_cmd']);
    }
  }

  protected function getFormValue(FormStateInterface $form_state, string $field, string $default): string {
    $value = $form_state->getValue([$field, 0, 'value']);
    if (is_string($value) && $value !== '') {
      return $value;
    }
    return $default;
  }

  protected function decodeConfig(?string $value): array {
    if (!$value) {
      return [];
    }
    $decoded = Json::decode($value);
    return is_array($decoded) ? $decoded : [];
  }

  protected function encodeConfig(array $value): string {
    return Json::encode($value);
  }

}
