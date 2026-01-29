<?php

namespace Drupal\hosting_task\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for hosting_task entities.
 */
class HostingTaskListBuilder extends EntityListBuilder {

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
      'label' => $this->t('Label'),
      'task_type' => $this->t('Task Type'),
      'status' => $this->t('Status'),
      'context_name' => $this->t('Context'),
      'started' => $this->t('Started'),
      'duration' => $this->t('Duration'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\hosting_task\Entity\HostingTask $entity */
    $row = [];
    
    $row['label'] = $entity->toLink($entity->label());
    $row['task_type'] = $entity->getTaskType();
    
    // Style status with color.
    $status = $entity->getStatus();
    $status_colors = [
      'queued' => 'orange',
      'processing' => 'blue',
      'success' => 'green',
      'error' => 'red',
      'warning' => 'orange',
    ];
    $color = $status_colors[$status] ?? 'gray';
    $row['status'] = [
      'data' => [
        '#markup' => '<span style="color: ' . $color . '; font-weight: bold;">' . $status . '</span>',
      ],
    ];
    
    $row['context_name'] = $entity->get('context_name')->value;
    
    // Format started time.
    $started = (int) $entity->get('started')->value;
    if ($started > 0) {
      $row['started'] = \Drupal::service('date.formatter')->format($started, 'short');
    } else {
      $row['started'] = $this->t('Not started');
    }
    
    // Calculate duration if both timestamps exist.
    $completed = (int) $entity->get('completed')->value;
    if ($started > 0 && $completed > 0) {
      $duration = $completed - $started;
      $row['duration'] = \Drupal::service('date.formatter')->formatInterval($duration);
    } elseif ($started > 0 && $completed === 0) {
      $row['duration'] = $this->t('Running...');
    } else {
      $row['duration'] = '-';
    }
    
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);
    
    // Add a "View log" operation if applicable.
    if ($entity->access('view') && $entity->hasLinkTemplate('canonical')) {
      $operations['view'] = [
        'title' => $this->t('View'),
        'weight' => 0,
        'url' => $entity->toUrl('canonical'),
      ];
    }
    
    return $operations;
  }

}
