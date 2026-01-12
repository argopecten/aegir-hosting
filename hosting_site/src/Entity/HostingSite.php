<?php

namespace Drupal\hosting_site\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Hosting site entity.
 *
 * @ContentEntityType(
 *   id = "hosting_site",
 *   label = @Translation("Hosting site"),
 *   base_table = "hosting_site",
 *   handlers = {
 *     "list_builder" = "Drupal\\Core\\Entity\\EntityListBuilder",
 *     "form" = {
 *       "default" = "Drupal\\hosting_site\\Form\\HostingSiteForm",
 *       "add" = "Drupal\\hosting_site\\Form\\HostingSiteForm",
 *       "edit" = "Drupal\\hosting_site\\Form\\HostingSiteForm",
 *       "delete" = "Drupal\\Core\\Entity\\ContentEntityDeleteForm"
 *     },
 *     "access" = "Drupal\\hosting_site\\Access\\HostingSiteAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\\Core\\Entity\\Routing\\AdminHtmlRouteProvider"
 *     }
 *   },
 *   admin_permission = "administer sites",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "domain"
 *   },
 *   links = {
 *     "canonical" = "/admin/hosting/sites/{hosting_site}",
 *     "add-form" = "/admin/hosting/sites/add",
 *     "edit-form" = "/admin/hosting/sites/{hosting_site}/edit",
 *     "delete-form" = "/admin/hosting/sites/{hosting_site}/delete",
 *     "collection" = "/admin/hosting/sites"
 *   }
 * )
 */
class HostingSite extends ContentEntityBase {

  public const STATUS_QUEUED = 0;
  public const STATUS_ENABLED = 1;
  public const STATUS_DISABLED = -1;
  public const STATUS_DELETED = -2;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['domain'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Domain'))
      ->setRequired(TRUE);

    $fields['client'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Client'))
      ->setSetting('target_type', 'hosting_client')
      ->setRequired(FALSE);

    $fields['platform'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Platform'))
      ->setSetting('target_type', 'hosting_platform')
      ->setRequired(TRUE);

    $fields['db_server'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Database server'))
      ->setSetting('target_type', 'hosting_server')
      ->setRequired(TRUE);

    $fields['db_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Database name'))
      ->setDefaultValue('');

    $fields['profile'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Profile'))
      ->setSetting('target_type', 'hosting_package')
      ->setRequired(FALSE);

    $fields['language'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Language'))
      ->setDefaultValue('en');

    $fields['last_cron'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last cron'))
      ->setDefaultValue(0);

    $fields['cron_key'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Cron key'))
      ->setDefaultValue('');

    $fields['verified'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Verified'))
      ->setDefaultValue(0);

    $fields['status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Status'))
      ->setDefaultValue(self::STATUS_QUEUED);

    return $fields;
  }

}
