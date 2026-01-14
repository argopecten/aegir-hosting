<?php

namespace Drupal\hosting_platform\Entity;

use Drupal\Core\Entity\ContentEntityBase;
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
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
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
 *     "label" = "publish_path"
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

    $fields['publish_path'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Publish path'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['makefile'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Makefile'))
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['web_server'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Web server'))
      ->setSetting('target_type', 'hosting_server')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['verified'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Verified'))
      ->setDefaultValue(0);

    $fields['status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Status'))
      ->setRequired(TRUE)
      ->setDefaultValue(self::STATUS_QUEUED);

    $fields['make_working_copy'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Make working copy'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
