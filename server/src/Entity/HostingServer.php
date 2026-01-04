<?php

namespace Drupal\hosting_server\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\hosting\Entity\HostingEntityBase;
use Drupal\hosting_server\Plugin\Field\HostingServerIpItemList;
use Drupal\hosting_server\Plugin\Field\HostingServerServicesItemList;

/**
 * Defines the Hosting server entity.
 *
 * @ContentEntityType(
 *   id = "hosting_server",
 *   label = @Translation("Hosting server"),
 *   handlers = {
 *     "list_builder" = "Drupal\hosting_server\HostingServerListBuilder",
 *     "access" = "Drupal\hosting_server\HostingServerAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\hosting_server\Form\HostingServerForm",
 *       "edit" = "Drupal\hosting_server\Form\HostingServerForm",
 *       "delete" = "Drupal\hosting_server\Form\HostingServerDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "hosting_server",
 *   admin_permission = "administer servers",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label"
 *   },
 *   links = {
 *     "canonical" = "/hosting/server/{hosting_server}",
 *     "add-form" = "/hosting/servers/add",
 *     "edit-form" = "/hosting/server/{hosting_server}/edit",
 *     "delete-form" = "/hosting/server/{hosting_server}/delete",
 *     "collection" = "/hosting/servers"
 *   }
 * )
 */
class HostingServer extends HostingEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label']
      ->setLabel(t('Server hostname'))
      ->setDescription(t('The host name of the server used by Hostmaster to connect and issue commands. Be careful when changing the hostname: it is used to create SQL users when no IP address is set.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['human_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Human-readable name'))
      ->setDescription(t('Optional, but recommended. Used throughout the UI for friendlier names.'))
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['verified'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Verified'))
      ->setDefaultValue(0);

    $fields['ip_address_list'] = BaseFieldDefinition::create('string')
      ->setLabel(t('IP addresses'))
      ->setComputed(TRUE)
      ->setClass(HostingServerIpItemList::class)
      ->setReadOnly(TRUE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED);

    $fields['service_types'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Services'))
      ->setComputed(TRUE)
      ->setClass(HostingServerServicesItemList::class)
      ->setReadOnly(TRUE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED);

    return $fields;
  }

}
