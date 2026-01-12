<?php

namespace Drupal\hosting_package\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Hosting package entity.
 *
 * @ContentEntityType(
 *   id = "hosting_package",
 *   label = @Translation("Hosting package"),
 *   base_table = "hosting_package",
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
 *     "label" = "short_name"
 *   },
 *   links = {
 *     "collection" = "/admin/hosting/packages"
 *   }
 * )
 */
class HostingPackage extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['package_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Package type'))
      ->setRequired(TRUE);

    $fields['short_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Short name'))
      ->setRequired(TRUE);

    $fields['old_short_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Old short name'))
      ->setDefaultValue('');

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'))
      ->setDefaultValue('');

    return $fields;
  }

}
