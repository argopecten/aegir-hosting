<?php

namespace Drupal\hosting_client\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\hosting\Entity\HostingEntityBase;

/**
 * Defines the Hosting client entity.
 *
 * @ContentEntityType(
 *   id = "hosting_client",
 *   label = @Translation("Hosting client"),
 *   handlers = {
 *     "list_builder" = "Drupal\hosting_client\HostingClientListBuilder",
 *     "access" = "Drupal\hosting_client\HostingClientAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\hosting_client\Form\HostingClientForm",
 *       "edit" = "Drupal\hosting_client\Form\HostingClientForm",
 *       "delete" = "Drupal\hosting_client\Form\HostingClientDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "hosting_client",
 *   admin_permission = "administer clients",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *     "owner" = "uid"
 *   },
 *   links = {
 *     "canonical" = "/hosting/client/{hosting_client}",
 *     "add-form" = "/hosting/clients/add",
 *     "edit-form" = "/hosting/client/{hosting_client}/edit",
 *     "delete-form" = "/hosting/client/{hosting_client}/delete",
 *     "collection" = "/hosting/clients"
 *   }
 * )
 */
class HostingClient extends HostingEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label']
      ->setLabel(t('Client name'))
      ->setDescription(t('The name of this client, generally the organization name or the contact name for individuals.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setSetting('target_type', 'user');

    $fields['uname'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Unix name'))
      ->setDescription(t('A machine-usable name that can be used internally, for example to map to a UNIX group in the backend.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setDescription(t('Email address of the contact created with this client.'))
      ->setSetting('max_length', 254)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['organization'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Organization'))
      ->setDescription(t('Organization associated with this client (optional).'))
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
