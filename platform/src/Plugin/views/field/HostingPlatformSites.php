<?php

namespace Drupal\hosting_platform\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Field handler to display the count of sites on a platform.
 *
 * @ViewsField("hosting_platform_sites")
 */
class HostingPlatformSites extends FieldPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a HostingPlatformSites object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\hosting_platform\Entity\HostingPlatform $entity */
    $entity = $this->getEntity($values);
    
    if (!$entity) {
      return '';
    }

    // Count sites on this platform
    $query = $this->entityTypeManager->getStorage('hosting_site')->getQuery()
      ->condition('platform', $entity->id())
      ->accessCheck(TRUE)
      ->count();
    
    $count = $query->execute();

    // Create a link to filtered site list
    if ($count > 0) {
      $url = Url::fromRoute('view.hosting_site_list.page_sites', [], [
        'query' => ['platform' => $entity->id()],
      ]);
      
      return [
        '#type' => 'link',
        '#title' => $count,
        '#url' => $url,
      ];
    }

    return $count;
  }

}
