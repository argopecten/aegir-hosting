<?php

namespace Drupal\hosting_package\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Hosting package instance entity.
 *
 * @ContentEntityType(
 *   id = "hosting_package_instance",
 *   label = @Translation("Hosting package instance"),
 *   base_table = "hosting_package_instance",
 *   handlers = {
 *     "list_builder" = "Drupal\\Core\\Entity\\EntityListBuilder",
 *     "form" = {
 *       "default" = "Drupal\\Core\\Entity\\ContentEntityForm",
 *       "add" = "Drupal\\Core\\Entity\\ContentEntityForm",
 *       "edit" = "Drupal\\Core\\Entity\\ContentEntityForm",
 *       "delete" = "Drupal\\Core\\Entity\\ContentEntityDeleteForm"
 *     },
 *     "access" = "Drupal\\Core\\Entity\\EntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\\Core\\Entity\\Routing\\AdminHtmlRouteProvider"
 *     }
 *   },
 *   admin_permission = "view package",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "version"
 *   },
 *   links = {
 *     "collection" = "/admin/hosting/package-instances"
 *   }
 * )
 */
class HostingPackageInstance extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['platform'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Platform'))
      ->setSetting('target_type', 'hosting_platform')
      ->setRequired(TRUE);

    $fields['platform_ref'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Platform reference'))
      ->setDefaultValue('');

    $fields['package'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Package'))
      ->setSetting('target_type', 'hosting_package')
      ->setRequired(TRUE);

    $fields['filename'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Filename'))
      ->setDefaultValue('');

    $fields['schema_version'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Schema version'))
      ->setDefaultValue(0);

    $fields['version'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Version'))
      ->setDefaultValue('');

    $fields['version_code'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Version code'))
      ->setDefaultValue(0);

    $fields['status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Status'))
      ->setDefaultValue(0);

    $fields['languages'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Languages'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDefaultValue([]);

    return $fields;
  }

}
