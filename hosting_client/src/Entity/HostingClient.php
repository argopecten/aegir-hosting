<?php

namespace Drupal\hosting_client\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Hosting client entity.
 *
 * @ContentEntityType(
 *   id = "hosting_client",
 *   label = @Translation("Hosting client"),
 *   base_table = "hosting_client",
 *   handlers = {
 *     "list_builder" = "Drupal\\Core\\Entity\\EntityListBuilder",
 *     "form" = {
 *       "default" = "Drupal\\hosting_client\\Form\\HostingClientForm",
 *       "add" = "Drupal\\hosting_client\\Form\\HostingClientForm",
 *       "edit" = "Drupal\\hosting_client\\Form\\HostingClientForm",
 *       "delete" = "Drupal\\Core\\Entity\\ContentEntityDeleteForm"
 *     },
 *     "access" = "Drupal\\hosting_client\\Access\\HostingClientAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\\Core\\Entity\\Routing\\AdminHtmlRouteProvider"
 *     }
 *   },
 *   admin_permission = "administer clients",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "name"
 *   },
 *   links = {
 *     "canonical" = "/admin/hosting/clients/{hosting_client}",
 *     "add-form" = "/admin/hosting/clients/add",
 *     "edit-form" = "/admin/hosting/clients/{hosting_client}/edit",
 *     "delete-form" = "/admin/hosting/clients/{hosting_client}/delete",
 *     "collection" = "/admin/hosting/clients"
 *   }
 * )
 */
class HostingClient extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setRequired(TRUE);

    $fields['uname'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Internal name'))
      ->setRequired(TRUE);

    $fields['owner'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setSetting('target_type', 'user')
      ->setRequired(FALSE);

    $fields['status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Status'))
      ->setDefaultValue(1);

    return $fields;
  }

}
