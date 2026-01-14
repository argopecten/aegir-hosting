<?php

namespace Drupal\hosting_server\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\hosting_server\Service\IpManager;
use Drupal\hosting_server\Service\ServerManager;
use Drupal\hosting_server\Service\ServiceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HostingServerForm extends ContentEntityForm {
  protected IpManager $ipManager;
  protected ServerManager $serverManager;
  protected ServiceManager $serviceManager;

  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, IpManager $ip_manager, ServerManager $server_manager, ServiceManager $service_manager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->ipManager = $ip_manager;
    $this->serverManager = $server_manager;
    $this->serviceManager = $service_manager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('hosting.ip_manager'),
      $container->get('hosting.server_manager'),
      $container->get('hosting.service_manager'),
    );
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $service_types = $this->serviceManager->getServiceTypes();
    if ($service_types) {
      $form['services'] = [
        '#type' => 'details',
        '#title' => $this->t('Services'),
        '#open' => TRUE,
        '#tree' => TRUE,
      ];

      $instances = $this->loadServiceInstances();

      foreach ($service_types as $service_type => $label) {
        $instance = $instances[$service_type] ?? NULL;
        $provider_options = $this->serviceManager->getProvidersForType($service_type);
        $provider_id = $this->getSelectedProviderId($form_state, $service_type, $instance, $provider_options);

        $form['services'][$service_type] = [
          '#type' => 'details',
          '#title' => $label,
          '#open' => TRUE,
          '#tree' => TRUE,
        ];
        $form['services'][$service_type]['provider'] = [
          '#type' => 'select',
          '#title' => $this->t('Provider'),
          '#options' => $provider_options,
          '#empty_option' => $this->t('- None -'),
          '#default_value' => $provider_id,
        ];
        $form['services'][$service_type]['available'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Available'),
          '#default_value' => $instance ? (bool) $instance->get('available')->value : TRUE,
        ];

        if ($provider_id) {
          $config = $instance && $instance->get('provider')->value === $provider_id
            ? $this->decodeConfig($instance->get('config')->value)
            : [];
          $provider = $this->serviceManager->createProvider($provider_id, $config);

          $form['services'][$service_type]['config'] = [
            '#type' => 'details',
            '#title' => $this->t('Settings'),
            '#open' => TRUE,
            '#tree' => TRUE,
          ];
          $subform_state = SubformState::createForSubform($form['services'][$service_type]['config'], $form, $form_state);
          $form['services'][$service_type]['config'] = $provider->buildConfigurationForm($form['services'][$service_type]['config'], $subform_state);
        }
      }
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $hostname = $this->getFieldValue($form_state->getValue('hostname'));
    if ($hostname && !$this->serverManager->isValidHostname($hostname)) {
      $form_state->setErrorByName('hostname', $this->t('Hostname must be a valid domain name or IP address.'));
    }

    $entity_id = $this->entity->id() ? (int) $this->entity->id() : NULL;
    if ($hostname && !$this->serverManager->isHostnameUnique($hostname, $entity_id)) {
      $form_state->setErrorByName('hostname', $this->t('Hostname must be unique.'));
    }

    $ip_values = $form_state->getValue('ip_addresses');
    if (is_array($ip_values)) {
      foreach ($ip_values as $delta => $item) {
        $ip = is_array($item) ? (string) ($item['value'] ?? '') : (string) $item;
        if ($ip === '') {
          continue;
        }
        if (!$this->ipManager->isValid($ip)) {
          $form_state->setErrorByName("ip_addresses][$delta][value", $this->t('Invalid IP address.'));
        }
      }
    }

    $services = $form_state->getValue('services') ?? [];
    $instances = $this->loadServiceInstances();
    foreach ($services as $service_type => $values) {
      $provider_id = (string) ($values['provider'] ?? '');
      if (!$provider_id) {
        continue;
      }
      $instance = $instances[$service_type] ?? NULL;
      $config = $instance && $instance->get('provider')->value === $provider_id
        ? $this->decodeConfig($instance->get('config')->value)
        : [];
      $provider = $this->serviceManager->createProvider($provider_id, $config);
      $subform = $form['services'][$service_type]['config'] ?? [];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      $provider->validateConfigurationForm($subform, $subform_state);
    }
  }

  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $this->saveServiceInstances($form, $form_state);
    return $result;
  }

  protected function saveServiceInstances(array $form, FormStateInterface $form_state): void {
    $services = $form_state->getValue('services') ?? [];
    if (!$services) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('hosting_service_instance');
    $instances = $this->loadServiceInstances();

    foreach ($services as $service_type => $values) {
      $provider_id = (string) ($values['provider'] ?? '');
      $instance = $instances[$service_type] ?? NULL;

      if (!$provider_id) {
        if ($instance) {
          $instance->delete();
        }
        continue;
      }

      $config = $instance && $instance->get('provider')->value === $provider_id
        ? $this->decodeConfig($instance->get('config')->value)
        : [];
      $provider = $this->serviceManager->createProvider($provider_id, $config);
      $subform = $form['services'][$service_type]['config'] ?? [];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      $provider->submitConfigurationForm($subform, $subform_state);
      $config = $provider->getConfiguration();

      if (!$instance) {
        $instance = $storage->create();
      }

      $instance->set('server', $this->entity->id());
      $instance->set('service_type', $service_type);
      $instance->set('provider', $provider_id);
      $instance->set('available', (bool) ($values['available'] ?? TRUE));
      $instance->set('config', $this->encodeConfig($config));

      if (array_key_exists('port', $config)) {
        $instance->set('port', (int) $config['port']);
      }
      if (array_key_exists('restart_cmd', $config)) {
        $instance->set('restart_cmd', (string) $config['restart_cmd']);
      }

      $instance->save();
    }
  }

  protected function loadServiceInstances(): array {
    $server_id = $this->entity->id();
    if (!$server_id) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('hosting_service_instance');
    $instances = $storage->loadByProperties(['server' => $server_id]);
    $map = [];
    foreach ($instances as $instance) {
      $service_type = (string) $instance->get('service_type')->value;
      if ($service_type !== '') {
        $map[$service_type] = $instance;
      }
    }
    return $map;
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

  protected function getSelectedProviderId(FormStateInterface $form_state, string $service_type, ?object $instance, array $provider_options): string {
    $selected = $form_state->getValue(['services', $service_type, 'provider']);
    if (is_string($selected) && $selected !== '') {
      return $selected;
    }
    if ($instance) {
      return (string) $instance->get('provider')->value;
    }
    if ($provider_options) {
      return (string) array_key_first($provider_options);
    }
    return '';
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
