<?php

namespace Drupal\hosting_site\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Hosting site backup entity.
 *
 * @ContentEntityType(
 *   id = "hosting_site_backup",
 *   label = @Translation("Hosting site backup"),
 *   base_table = "hosting_site_backup",
 *   handlers = {
 *     "list_builder" = "Drupal\\Core\\Entity\\EntityListBuilder",
 *     "form" = {
 *       "default" = "Drupal\\Core\\Entity\\ContentEntityForm",
 *       "add" = "Drupal\\Core\\Entity\\ContentEntityForm",
 *       "edit" = "Drupal\\Core\\Entity\\ContentEntityForm",
 *       "delete" = "Drupal\\Core\\Entity\\ContentEntityDeleteForm"
 *     },
 *     "access" = "Drupal\\hosting_site\\Access\\HostingSiteBackupAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\\Core\\Entity\\Routing\\AdminHtmlRouteProvider"
 *     }
 *   },
 *   admin_permission = "administer sites",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "filename"
 *   },
 *   links = {
 *     "collection" = "/admin/hosting/site-backups"
 *   }
 * )
 */
class HostingSiteBackup extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['site'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Site'))
      ->setSetting('target_type', 'hosting_site')
      ->setRequired(TRUE);

    $fields['web_server'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Web server'))
      ->setSetting('target_type', 'hosting_server')
      ->setRequired(TRUE);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'))
      ->setDefaultValue('');

    $fields['filename'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Filename'))
      ->setDefaultValue('');

    $fields['size'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Size'))
      ->setDefaultValue(0);

    $fields['timestamp'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Timestamp'))
      ->setDefaultValue(0);

    return $fields;
  }

}
