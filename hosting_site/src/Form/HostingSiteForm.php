<?php

namespace Drupal\hosting_site\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hosting_site\Service\SiteManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HostingSiteForm extends ContentEntityForm {

  protected SiteManager $siteManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, SiteManager $site_manager) {
    parent::__construct($entity_type_manager);
    $this->siteManager = $site_manager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('hosting_site.manager'),
    );
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $domain = $this->getFieldValue($form_state->getValue('domain'));
    $domain = $this->siteManager->normalizeDomain($domain);
    $form_state->setValue(['domain', 0, 'value'], $domain);

    if ($domain && !$this->siteManager->isDomainValid($domain)) {
      $form_state->setErrorByName('domain', $this->t('Domain must be a valid hostname.'));
    }

    $entity_id = $this->entity->id() ? (int) $this->entity->id() : NULL;
    if ($domain && !$this->siteManager->isDomainUnique($domain, $entity_id)) {
      $form_state->setErrorByName('domain', $this->t('Domain must be unique.'));
    }
  }

  public function save(array $form, FormStateInterface $form_state): int {
    if ($this->entity->isNew() && !$this->entity->get('cron_key')->value) {
      $this->entity->set('cron_key', $this->siteManager->generateCronKey());
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
