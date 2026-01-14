<?php

namespace Drupal\hosting_client\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hosting_client\Service\ClientManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HostingClientForm extends ContentEntityForm {

  protected ClientManager $clientManager;

  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, ClientManager $client_manager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->clientManager = $client_manager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('hosting_client.manager'),
    );
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    if (isset($form['uname']['widget'][0]['value'])) {
      $form['uname']['widget'][0]['value']['#type'] = 'machine_name';
      $form['uname']['widget'][0]['value']['#machine_name'] = [
        'source' => ['name', 'widget', 0, 'value'],
        'exists' => [$this, 'unameExists'],
      ];
      $form['uname']['widget'][0]['value']['#disabled'] = !$this->currentUser()->hasPermission('edit client uname') && !$this->entity->isNew();
    }

    if (isset($form['owner']['widget'][0]['target_id']) && $this->entity->isNew()) {
      $form['owner']['widget'][0]['target_id']['#default_value'] = $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id());
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $uname = (string) $form_state->getValue(['uname', 0, 'value']);
    $uname = $this->clientManager->sanitizeUname($uname);
    $form_state->setValue(['uname', 0, 'value'], $uname);

    $entity_id = $this->entity->id() ? (int) $this->entity->id() : NULL;
    if ($uname && !$this->clientManager->isUnameUnique($uname, $entity_id)) {
      $form_state->setErrorByName('uname', $this->t('Client machine name must be unique.'));
    }

    if (!$this->currentUser()->hasPermission('edit client uname') && !$this->entity->isNew()) {
      $original = (string) $this->entity->get('uname')->value;
      if ($uname !== $original) {
        $form_state->setErrorByName('uname', $this->t('You do not have permission to edit the client machine name.'));
      }
    }
  }

  public function unameExists(string $uname): bool {
    $entity_id = $this->entity->id() ? (int) $this->entity->id() : NULL;
    return !$this->clientManager->isUnameUnique($uname, $entity_id);
  }

}
