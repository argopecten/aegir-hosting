<?php

namespace Drupal\hosting_client\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for hosting_client entities.
 */
class HostingClientListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id())
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'name' => $this->t('Name'),
      'uname' => $this->t('Internal Name'),
      'owner' => $this->t('Owner'),
      'users' => $this->t('Users'),
      'sites' => $this->t('Sites'),
      'status' => $this->t('Status'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\hosting_client\Entity\HostingClient $entity */
    $row = [];
    
    $row['name'] = $entity->toLink($entity->get('name')->value);
    $row['uname'] = $entity->get('uname')->value;
    
    // Get owner information.
    $owner = $entity->get('owner')->entity;
    if ($owner) {
      $row['owner'] = $owner->toLink($owner->getDisplayName());
    } else {
      $row['owner'] = $this->t('None');
    }
    
    // Count associated users.
    $storage = \Drupal::entityTypeManager()->getStorage('hosting_client_user');
    $user_count = $storage->getQuery()
      ->condition('client', $entity->id())
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $row['users'] = $user_count;
    
    // Count associated sites.
    $site_storage = \Drupal::entityTypeManager()->getStorage('hosting_site');
    $site_count = $site_storage->getQuery()
      ->condition('client', $entity->id())
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $row['sites'] = $site_count;
    
    // Get status.
    $status = (int) $entity->get('status')->value;
    if ($status === 1) {
      $row['status'] = [
        'data' => [
          '#markup' => '<span style="color: green; font-weight: bold;">' . $this->t('Active') . '</span>',
        ],
      ];
    } else {
      $row['status'] = [
        'data' => [
          '#markup' => '<span style="color: gray;">' . $this->t('Inactive') . '</span>',
        ],
      ];
    }
    
    return $row + parent::buildRow($entity);
  }

}
