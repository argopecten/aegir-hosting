<?php

namespace Drupal\hosting_platform\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\Markup;
use Drupal\hosting_platform\Service\PlatformManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HostingPlatformListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  protected string $iconSpritePath;

  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    $instance = new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id())
    );
    try {
      $theme_path = $container->get('extension.list.theme')->getPath('aegir_eldir');
    }
    catch (\Throwable) {
      $theme_path = 'themes/contrib/aegir-eldir';
    }
    $instance->iconSpritePath = base_path() . $theme_path . '/images/svg/aegir-icons-sprite.svg';
    return $instance;
  }

  public function buildHeader(): array {
    return [
      'name' => $this->t('Name'),
      'path' => $this->t('Platform Path'),
      'web_server' => $this->t('Web Server'),
      'status' => $this->t('Status'),
      'verified' => $this->t('Last Verified'),
    ];
  }

  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\hosting_platform\Entity\HostingPlatform $entity */
    $row = [];
    
    $name = $entity->get('name')->value;
    $publish_path = $entity->get('publish_path')->value;
    
    $row['name'] = $entity->toLink($name);
    
    // Check if path exists — use SVG icons.
    $path_exists = is_dir($publish_path);
    $path_icon = $path_exists ? 'icon-ok' : 'icon-error';
    $icon_svg = '<svg class="hosting-icon" aria-hidden="true"><use href="' . $this->iconSpritePath . '#' . $path_icon . '"></use></svg>';
    $row['path'] = [
      'data' => [
        '#markup' => Markup::create($icon_svg . ' ' . htmlspecialchars($publish_path)),
      ],
    ];
    
    // Get web server name
    $web_server = $entity->get('web_server')->entity;
    if ($web_server) {
      $server_name = $web_server->get('human_name')->value ?: $web_server->get('hostname')->value;
      $row['web_server'] = $server_name;
    } else {
      $row['web_server'] = $this->t('None');
    }
    
    // Get status
    $status_value = (int) $entity->get('status')->value;
    $status_labels = [
      HostingPlatform::STATUS_QUEUED => $this->t('Queued'),
      HostingPlatform::STATUS_ENABLED => $this->t('Enabled'),
      HostingPlatform::STATUS_LOCKED => $this->t('Locked'),
      HostingPlatform::STATUS_DELETED => $this->t('Deleted'),
    ];
    $row['status'] = $status_labels[$status_value] ?? $this->t('Unknown');
    
    // Get verified timestamp
    $verified = (int) $entity->get('verified')->value;
    if ($verified > 0) {
      $row['verified'] = \Drupal::service('date.formatter')->format($verified, 'short');
    } else {
      $row['verified'] = $this->t('Never');
    }
    
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['table']['#attributes']['class'][] = 'hosting-table';
    $build['table']['#attributes']['class'][] = 'hosting-platform-list';
    $build['#cache']['tags'][] = 'hosting_platform_list';

    if (!empty($build['table']['#rows'])) {
      $entities = $this->load();
      $css_map = [
        HostingPlatform::STATUS_ENABLED => 'hosting-success',
        HostingPlatform::STATUS_QUEUED => 'hosting-queue',
        HostingPlatform::STATUS_LOCKED => 'hosting-warning',
        HostingPlatform::STATUS_DELETED => 'hosting-error',
      ];
      $idx = 0;
      foreach ($entities as $entity) {
        $status_val = (int) $entity->get('status')->value;
        $css = $css_map[$status_val] ?? '';
        if ($css && isset($build['table']['#rows'][$idx])) {
          $build['table']['#rows'][$idx]['class'][] = $css;
        }
        $idx++;
      }
    }

    return $build;
  }

}

