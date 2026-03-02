<?php

namespace Drupal\hosting_task\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for hosting_task entities.
 */
class HostingTaskListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  protected string $iconSpritePath;

  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    $instance = new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id())
    );
    $instance->iconSpritePath = $container->get('hosting.icon_provider')->getSpriteUrl();
    return $instance;
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
    
    // Status with SVG icon and CSS class.
    $status = $entity->getStatus();
    $icon_map = [
      'success' => 'icon-ok',
      'error' => 'icon-error',
      'warning' => 'icon-warning',
      'queued' => 'icon-queue',
      'processing' => 'icon-info',
    ];
    $icon_id = $icon_map[$status] ?? 'icon-queue';
    $icon = '<svg class="hosting-icon" aria-hidden="true"><use href="' . $this->iconSpritePath . '#' . $icon_id . '"></use></svg>';
    $row['status'] = [
      'data' => [
        '#markup' => Markup::create($icon . '<strong>' . ucfirst($status) . '</strong>'),
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

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['table']['#attributes']['class'][] = 'hosting-table';
    $build['table']['#attributes']['class'][] = 'hosting-task-list';
    $build['#cache']['tags'][] = 'hosting_task_list';

    if (!empty($build['table']['#rows'])) {
      $entities = $this->load();
      $css_map = [
        'success' => 'hosting-success',
        'error' => 'hosting-error',
        'warning' => 'hosting-warning',
        'queued' => 'hosting-queue',
        'processing' => 'hosting-info',
      ];
      $idx = 0;
      foreach ($entities as $entity) {
        $status = $entity->getStatus();
        $css = $css_map[$status] ?? '';
        if ($css && isset($build['table']['#rows'][$idx])) {
          $build['table']['#rows'][$idx]['class'][] = $css;
        }
        $idx++;
      }
    }

    return $build;
  }

}
