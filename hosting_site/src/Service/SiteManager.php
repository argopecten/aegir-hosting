<?php

namespace Drupal\hosting_site\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class SiteManager {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected ConfigFactoryInterface $configFactory;
  protected DomainValidator $domainValidator;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, DomainValidator $domain_validator) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->domainValidator = $domain_validator;
  }

  public function normalizeDomain(string $domain): string {
    return $this->domainValidator->normalize($domain);
  }

  public function isDomainValid(string $domain): bool {
    return $this->domainValidator->isValid($domain);
  }

  public function isDomainUnique(string $domain, ?int $exclude_id = NULL): bool {
    $query = $this->entityTypeManager
      ->getStorage('hosting_site')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('domain', $domain);

    if ($exclude_id) {
      $query->condition('id', $exclude_id, '<>');
    }

    return $query->count()->execute() == 0;
  }

  public function generateCronKey(): string {
    return substr(hash('sha256', random_bytes(16)), 0, 32);
  }

  public function requireDisableBeforeDelete(): bool {
    $config = $this->configFactory->get('hosting.settings');
    return (bool) $config->get('settings.require_disable_before_delete');
  }

  public function forceDeleteEnabled(): bool {
    $config = $this->configFactory->get('hosting.settings');
    return (bool) $config->get('settings.delete_force');
  }

}
