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
 *     "view_builder" = "Drupal\hosting_client\Entity\HostingClientViewBuilder",
 *     "list_builder" = "Drupal\hosting_client\Entity\HostingClientListBuilder",
 *     "form" = {
 *       "default" = "Drupal\hosting_client\Form\HostingClientForm",
 *       "add" = "Drupal\hosting_client\Form\HostingClientForm",
 *       "edit" = "Drupal\hosting_client\Form\HostingClientForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "access" = "Drupal\hosting_client\Access\HostingClientAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider"
 *     }
 *   },
 *   admin_permission = "administer clients",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "name"
 *   },
 *   links = {
 *     "canonical" = "/hosting/clients/{hosting_client}",
 *     "add-form" = "/hosting/clients/add",
 *     "edit-form" = "/hosting/clients/{hosting_client}/edit",
 *     "delete-form" = "/hosting/clients/{hosting_client}/delete",
 *     "collection" = "/hosting/clients"
 *   }
 * )
 */
class HostingClient extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['uname'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Internal name'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['owner'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setSetting('target_type', 'user')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Status'))
      ->setDefaultValue(1)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
