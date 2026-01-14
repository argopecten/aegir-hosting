<?php

namespace Drupal\hosting_server\Entity;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\Markup;
use Drupal\hosting_server\Service\ServiceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HostingServerListBuilder extends EntityListBuilder {

  protected EntityStorageInterface $serviceInstanceStorage;
  protected ServiceManager $serviceManager;

  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityStorageInterface $service_instance_storage, ServiceManager $service_manager) {
    parent::__construct($entity_type, $storage);
    $this->serviceInstanceStorage = $service_instance_storage;
    $this->serviceManager = $service_manager;
  }

  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $entity_type,
      $entity_type_manager->getStorage($entity_type->id()),
      $entity_type_manager->getStorage('hosting_service_instance'),
      $container->get('hosting.service_manager'),
    );
  }

  public function buildHeader(): array {
    return [
      'name' => $this->t('Name'),
      'certificate' => $this->t('Certificate'),
      'database' => $this->t('Database'),
      'remote_import' => $this->t('Remote Import'),
      'web' => $this->t('Web'),
    ];
  }

  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\hosting_server\Entity\HostingServer $entity */
    $display_name = (string) ($entity->get('human_name')->value ?: $entity->label());

    $instances = $this->serviceInstanceStorage->loadByProperties(['server' => $entity->id()]);
    $service_instances = [];
    foreach ($instances as $instance) {
      $service_type = (string) $instance->get('service_type')->value;
      if ($service_type !== '') {
        $service_instances[$service_type] = $instance;
      }
    }

    $row = [];
    $row['name'] = $entity->toLink($display_name)->toString();
    $row['certificate'] = $this->statusCell($this->t('no'), FALSE);
    $row['database'] = $this->serviceCell($service_instances, 'db');
    $row['remote_import'] = $this->statusCell($this->t('no'), FALSE);
    $row['web'] = $this->serviceCell($service_instances, 'http');

    return $row;
  }

  protected function serviceCell(array $service_instances, string $service_type): array {
    $instance = $service_instances[$service_type] ?? NULL;
    if (!$instance) {
      return $this->statusCell($this->t('no'), FALSE);
    }

    $providers = $this->serviceManager->getProvidersForType($service_type);
    $provider_id = (string) $instance->get('provider')->value;
    $label = (string) ($providers[$provider_id] ?? $provider_id);
    $available = (bool) $instance->get('available')->value;

    $text = $available ? $label : (string) $this->t('no');
    return $this->statusCell($text, $available);
  }

  protected function statusCell(string $text, bool $available): array {
    $class = $available ? 'yes' : 'no';
    $markup = '<span class="hosting-status hosting-status--' . $class . '">' . Html::escape($text) . '</span>';
    return [
      'data' => Markup::create($markup),
    ];
  }

}
