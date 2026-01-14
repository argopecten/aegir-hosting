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

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $publish_path = $this->getFieldValue($form_state->getValue('publish_path'));
    if ($publish_path && !$this->platformManager->isValidPublishPath($publish_path)) {
      $form_state->setErrorByName('publish_path', $this->t('Publish path must be an absolute path.'));
    }

    $entity_id = $this->entity->id() ? (int) $this->entity->id() : NULL;
    if ($publish_path && !$this->platformManager->isPublishPathUnique($publish_path, $entity_id)) {
      $form_state->setErrorByName('publish_path', $this->t('Publish path must be unique.'));
    }
  }

  public function save(array $form, FormStateInterface $form_state): int {
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
