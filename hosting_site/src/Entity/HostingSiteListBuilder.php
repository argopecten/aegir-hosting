<?php

namespace Drupal\hosting_site\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * List builder for hosting_site entities.
 *
 * Replicates the D7 Aegir /hosting/sites table with status icon,
 * domain link, profile, created date, and platform columns.
 */
class HostingSiteListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Path to the SVG icon sprite.
   *
   * @var string
   */
  protected string $iconSpritePath;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    $instance = new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
    );
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->iconSpritePath = $container->get('hosting.icon_provider')->getSpriteUrl();
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'icon' => [
        'data' => '',
        'class' => ['hosting-icon-col'],
      ],
      'domain' => [
        'data' => $this->t('Site'),
        'field' => 'domain',
        'specifier' => 'domain',
      ],
      'profile' => $this->t('Profile'),
      'created' => [
        'data' => $this->t('Created'),
        'field' => 'verified',
        'specifier' => 'verified',
      ],
      'platform' => $this->t('Platform'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\hosting_site\Entity\HostingSite $entity */
    $status_val = (int) $entity->get('status')->value;

    $icon_map = [
      HostingSite::STATUS_ENABLED => 'icon-ok',
      HostingSite::STATUS_QUEUED => 'icon-queue',
      HostingSite::STATUS_DISABLED => 'icon-disabled',
      HostingSite::STATUS_DELETED => 'icon-error',
    ];
    $icon_id = $icon_map[$status_val] ?? 'icon-queue';

    $row['icon'] = [
      'data' => [
        '#markup' => Markup::create('<svg class="hosting-icon" aria-hidden="true"><use href="' . $this->iconSpritePath . '#' . $icon_id . '"></use></svg>'),
      ],
      'class' => ['hosting-icon-col'],
    ];

    $row['domain'] = [
      'data' => $entity->toLink($entity->get('domain')->value),
    ];

    // Profile name.
    $profile_label = '';
    if ($entity->hasField('profile') && !$entity->get('profile')->isEmpty()) {
      $profile = $entity->get('profile')->entity;
      if ($profile) {
        $profile_label = $profile->label();
      }
    }
    $row['profile'] = $profile_label;

    // Created / verified date.
    $verified = (int) $entity->get('verified')->value;
    if ($verified > 0) {
      $row['created'] = $this->dateFormatter->formatTimeDiffSince($verified) . ' ' . $this->t('ago');
    }
    else {
      $row['created'] = $this->t('Not verified');
    }

    // Platform name.
    $platform_label = '';
    if ($entity->hasField('platform') && !$entity->get('platform')->isEmpty()) {
      $platform = $entity->get('platform')->entity;
      if ($platform) {
        $platform_label = $platform->toLink($platform->label());
      }
    }
    $row['platform'] = ['data' => $platform_label];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();

    // Add status-based row classes.
    if (!empty($build['table']['#rows'])) {
      $entities = $this->load();
      $idx = 0;
      foreach ($entities as $entity) {
        /** @var \Drupal\hosting_site\Entity\HostingSite $entity */
        $status_val = (int) $entity->get('status')->value;
        $css_map = [
          HostingSite::STATUS_ENABLED => 'hosting-success',
          HostingSite::STATUS_QUEUED => 'hosting-queue',
          HostingSite::STATUS_DISABLED => 'hosting-disable',
          HostingSite::STATUS_DELETED => 'hosting-error',
        ];
        $css_class = $css_map[$status_val] ?? '';
        if ($css_class && isset($build['table']['#rows'][$idx])) {
          $build['table']['#rows'][$idx]['class'][] = $css_class;
        }
        $idx++;
      }
    }

    $build['table']['#attributes']['class'][] = 'hosting-table';
    $build['table']['#attributes']['class'][] = 'hosting-site-list';
    $build['#cache']['tags'][] = 'hosting_site_list';

    return $build;
  }

}
