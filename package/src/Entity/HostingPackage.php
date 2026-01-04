<?php

namespace Drupal\hosting_package\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\hosting\Entity\HostingEntityBase;

/**
 * Defines the Hosting package entity.
 *
 * @ContentEntityType(
 *   id = "hosting_package",
 *   label = @Translation("Hosting package"),
 *   handlers = {
 *     "list_builder" = "Drupal\hosting_package\HostingPackageListBuilder",
 *     "access" = "Drupal\hosting_package\HostingPackageAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\hosting_package\Form\HostingPackageForm",
 *       "edit" = "Drupal\hosting_package\Form\HostingPackageForm",
 *       "delete" = "Drupal\hosting_package\Form\HostingPackageDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "hosting_package",
 *   admin_permission = "view package",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label"
 *   },
 *   links = {
 *     "canonical" = "/hosting/package/{hosting_package}",
 *     "add-form" = "/hosting/packages/add",
 *     "edit-form" = "/hosting/package/{hosting_package}/edit",
 *     "delete-form" = "/hosting/package/{hosting_package}/delete",
 *     "collection" = "/hosting/packages"
 *   }
 * )
 */
class HostingPackage extends HostingEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label']->setLabel(t('Package'));

    $fields['package_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Package type'))
      ->setSetting('max_length', 32);

    $fields['short_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Short name'))
      ->setSetting('max_length', 255);

    $fields['old_short_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Previous short name'))
      ->setSetting('max_length', 255);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'));

    return $fields;
  }

}
