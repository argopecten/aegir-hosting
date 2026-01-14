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
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "form" = {
 *       "default" = "Drupal\hosting_server\Form\HostingServiceInstanceForm",
 *       "add" = "Drupal\hosting_server\Form\HostingServiceInstanceForm",
 *       "edit" = "Drupal\hosting_server\Form\HostingServiceInstanceForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "access" = "Drupal\hosting_server\Access\HostingServiceInstanceAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider"
 *     }
 *   },
 *   admin_permission = "administer servers",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "provider"
 *   },
 *   links = {
 *     "canonical" = "/hosting/services/{hosting_service_instance}",
 *     "add-form" = "/hosting/services/add",
 *     "edit-form" = "/hosting/services/{hosting_service_instance}/edit",
 *     "delete-form" = "/hosting/services/{hosting_service_instance}/delete",
 *     "collection" = "/hosting/services"
 *   }
 * )
 */
class HostingServiceInstance extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['server'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Server'))
      ->setSetting('target_type', 'hosting_server')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['service_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Service type'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['provider'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Provider'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['available'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Available'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['port'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Port'))
      ->setDefaultValue(0)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['restart_cmd'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Restart command'))
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['config'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Provider configuration'))
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
