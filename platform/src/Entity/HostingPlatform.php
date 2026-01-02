<?php

namespace Drupal\hosting_platform\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\hosting\Entity\HostingEntityBase;
use Drupal\hosting_platform\Plugin\Field\HostingPlatformClientsItemList;

/**
 * Defines the Hosting platform entity.
 *
 * @ContentEntityType(
 *   id = "hosting_platform",
 *   label = @Translation("Hosting platform"),
 *   handlers = {
 *     "list_builder" = "Drupal\hosting_platform\HostingPlatformListBuilder",
 *     "access" = "Drupal\hosting_platform\HostingPlatformAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\hosting_platform\Form\HostingPlatformForm",
 *       "edit" = "Drupal\hosting_platform\Form\HostingPlatformForm",
 *       "delete" = "Drupal\hosting_platform\Form\HostingPlatformDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "hosting_platform",
 *   admin_permission = "administer platforms",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label"
 *   },
 *   links = {
 *     "canonical" = "/hosting/platform/{hosting_platform}",
 *     "add-form" = "/hosting/platforms/add",
 *     "edit-form" = "/hosting/platform/{hosting_platform}/edit",
 *     "delete-form" = "/hosting/platform/{hosting_platform}/delete",
 *     "collection" = "/hosting/platforms"
 *   }
 * )
 */
class HostingPlatform extends HostingEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label']
      ->setLabel(t('Platform name'))
      ->setDescription(t('Choose a unique descriptive name for your platform, for example "Drupal 11.0".'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['publish_path'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Publish path'))
      ->setDescription(t('The absolute path where sites will be hosted. This should exist and be unique across all servers.'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['web_server'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Web server'))
      ->setDescription(t('The web server the sites will be hosted on.'))
      ->setSetting('target_type', 'hosting_server')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['verified'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Verified'))
      ->setDefaultValue(0);

    $fields['clients'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Clients'))
      ->setSetting('target_type', 'hosting_client')
      ->setComputed(TRUE)
      ->setClass(HostingPlatformClientsItemList::class)
      ->setReadOnly(TRUE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED);

    return $fields;
  }

}
