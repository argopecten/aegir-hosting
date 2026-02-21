<?php

namespace Drupal\hosting_platform\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Hosting platform entity.
 *
 * @ContentEntityType(
 *   id = "hosting_platform",
 *   label = @Translation("Hosting platform"),
 *   base_table = "hosting_platform",
 *   handlers = {
 *     "view_builder" = "Drupal\hosting_platform\Entity\HostingPlatformViewBuilder",
 *     "list_builder" = "Drupal\hosting_platform\Entity\HostingPlatformListBuilder",
 *     "form" = {
 *       "default" = "Drupal\hosting_platform\Form\HostingPlatformForm",
 *       "add" = "Drupal\hosting_platform\Form\HostingPlatformForm",
 *       "edit" = "Drupal\hosting_platform\Form\HostingPlatformForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "access" = "Drupal\hosting_platform\Access\HostingPlatformAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider"
 *     }
 *   },
 *   admin_permission = "administer platforms",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "name"
 *   },
 *   links = {
 *     "canonical" = "/hosting/platforms/{hosting_platform}",
 *     "add-form" = "/hosting/platforms/add",
 *     "edit-form" = "/hosting/platforms/{hosting_platform}/edit",
 *     "delete-form" = "/hosting/platforms/{hosting_platform}/delete",
 *     "collection" = "/hosting/platforms"
 *   }
 * )
 */
class HostingPlatform extends ContentEntityBase {

  public const STATUS_QUEUED = 0;
  public const STATUS_ENABLED = 1;
  public const STATUS_LOCKED = -1;
  public const STATUS_DELETED = -2;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Platform name'))
      ->setDescription(t('A descriptive name for this platform (e.g., "Drupal 11.2").'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -2,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['platform_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Platform source'))
      ->setDescription(t('How the platform will be deployed.'))
      ->setRequired(TRUE)
      ->setDefaultValue('manual')
      ->setSetting('allowed_values', [
        'manual' => 'Manual (existing directory)',
        'git' => 'Git repository (GitHub/GitLab)',
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => -1,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['git_url'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Git repository URL'))
      ->setDescription(t('URL of the Git repository (e.g., https://github.com/username/drupal-project).'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['publish_path'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Publish path'))
      ->setDescription(t('Absolute path to the Drupal platform root directory within /var/aegir/platforms. Must contain a composer.json file.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['web_server'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Web server'))
      ->setSetting('target_type', 'hosting_server')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['verified'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Verified'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Status'))
      ->setRequired(TRUE)
      ->setDefaultValue(self::STATUS_QUEUED)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);

    // Register this platform in the context registry.
    $context_name = 'platform_' . $this->id();
    /** @var \Drupal\hosting\Service\ContextRegistry $registry */
    $registry = \Drupal::service('hosting.context_registry');
    $registry->register($context_name, 'hosting_platform', (int) $this->id());

    if (!$update) {
      // New platform: queue a save task (creates backend context), then verify.
      /** @var \Drupal\hosting_task\Service\TaskManagerInterface $task_manager */
      $task_manager = \Drupal::service('hosting.task_manager');
      $provision_data = $this->getProvisionContextData();
      $task_manager->createTask($context_name, 'save', [], [
        'data' => json_encode($provision_data),
        'type' => 'platform',
      ], 'verify');
    }
  }

  /**
   * Build the provision context data array from entity fields.
   *
   * Maps Drupal entity fields to the provision backend's expected keys.
   *
   * @return array
   *   Associative array of provision context data.
   */
  public function getProvisionContextData(): array {
    $data = [
      'root' => $this->get('publish_path')->value,
    ];

    // Map web_server entity reference to provision context name.
    $web_server_id = $this->get('web_server')->target_id;
    if ($web_server_id) {
      $data['server'] = 'server_' . $web_server_id;
    }

    return $data;
  }

}
