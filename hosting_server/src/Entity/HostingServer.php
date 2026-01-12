<?php

namespace Drupal\hosting_server\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Hosting server entity.
 *
 * @ContentEntityType(
 *   id = "hosting_server",
 *   label = @Translation("Hosting server"),
 *   base_table = "hosting_server",
 *   handlers = {
 *     "list_builder" = "Drupal\\Core\\Entity\\EntityListBuilder",
 *     "form" = {
 *       "default" = "Drupal\\hosting_server\\Form\\HostingServerForm",
 *       "add" = "Drupal\\hosting_server\\Form\\HostingServerForm",
 *       "edit" = "Drupal\\hosting_server\\Form\\HostingServerForm",
 *       "delete" = "Drupal\\Core\\Entity\\ContentEntityDeleteForm"
 *     },
 *     "access" = "Drupal\\hosting_server\\Access\\HostingServerAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\\Core\\Entity\\Routing\\AdminHtmlRouteProvider"
 *     }
 *   },
 *   admin_permission = "administer servers",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "hostname"
 *   },
 *   links = {
 *     "canonical" = "/admin/hosting/servers/{hosting_server}",
 *     "add-form" = "/admin/hosting/servers/add",
 *     "edit-form" = "/admin/hosting/servers/{hosting_server}/edit",
 *     "delete-form" = "/admin/hosting/servers/{hosting_server}/delete",
 *     "collection" = "/admin/hosting/servers"
 *   }
 * )
 */
class HostingServer extends ContentEntityBase {

  public const STATUS_QUEUED = 0;
  public const STATUS_ENABLED = 1;
  public const STATUS_LOCKED = -1;
  public const STATUS_DELETED = -2;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['hostname'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Hostname'))
      ->setRequired(TRUE);

    $fields['human_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Human-readable name'))
      ->setRequired(FALSE);

    $fields['status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Status'))
      ->setRequired(TRUE)
      ->setDefaultValue(self::STATUS_QUEUED);

    $fields['verified'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Verified'))
      ->setDefaultValue(0);

    $fields['ip_addresses'] = BaseFieldDefinition::create('string')
      ->setLabel(t('IP addresses'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setRequired(FALSE);

    return $fields;
  }

}
