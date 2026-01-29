<?php

namespace Drupal\hosting_task\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * View builder for hosting_task entities.
 */
class HostingTaskViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL): array {
    $build = [
      '#theme' => 'hosting_task',
      '#hosting_task' => $entity,
      '#view_mode' => $view_mode,
    ];

    // Attach the hosting_task library for styling.
    $build['#attached']['library'][] = 'hosting_task/hosting-task';

    return $build;
  }

}
