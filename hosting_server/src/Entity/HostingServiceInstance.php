<?php

namespace Drupal\hosting_server\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Hosting service instance entity.
 *
 * @ContentEntityType(
 *   id = "hosting_service_instance",
 *   label = @Translation("Hosting service instance"),
 *   base_table = "hosting_service_instance",
 *   handlers = {
 *     "list_builder" = "Drupal\\Core\\Entity\\EntityListBuilder",
 *     "form" = {
 *       "default" = "Drupal\\hosting_server\\Form\\HostingServiceInstanceForm",
 *       "add" = "Drupal\\hosting_server\\Form\\HostingServiceInstanceForm",
 *       "edit" = "Drupal\\hosting_server\\Form\\HostingServiceInstanceForm",
 *       "delete" = "Drupal\\Core\\Entity\\ContentEntityDeleteForm"
 *     },
 *     "access" = "Drupal\\hosting_server\\Access\\HostingServiceInstanceAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\\Core\\Entity\\Routing\\AdminHtmlRouteProvider"
 *     }
 *   },
 *   admin_permission = "administer servers",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "provider"
 *   },
 *   links = {
 *     "canonical" = "/admin/hosting/services/{hosting_service_instance}",
 *     "add-form" = "/admin/hosting/services/add",
 *     "edit-form" = "/admin/hosting/services/{hosting_service_instance}/edit",
 *     "delete-form" = "/admin/hosting/services/{hosting_service_instance}/delete",
 *     "collection" = "/admin/hosting/services"
 *   }
 * )
 */
class HostingServiceInstance extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['server'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Server'))
      ->setSetting('target_type', 'hosting_server')
      ->setRequired(TRUE);

    $fields['service_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Service type'))
      ->setRequired(TRUE);

    $fields['provider'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Provider'))
      ->setRequired(TRUE);

    $fields['available'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Available'))
      ->setDefaultValue(TRUE);

    $fields['port'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Port'))
      ->setDefaultValue(0);

    $fields['restart_cmd'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Restart command'))
      ->setDefaultValue('');

    $fields['config'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Provider configuration'))
      ->setDefaultValue('');

    return $fields;
  }

}
