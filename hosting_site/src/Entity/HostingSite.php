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
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "form" = {
 *       "default" = "Drupal\hosting_site\Form\HostingSiteForm",
 *       "add" = "Drupal\hosting_site\Form\HostingSiteForm",
 *       "edit" = "Drupal\hosting_site\Form\HostingSiteForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "access" = "Drupal\hosting_site\Access\HostingSiteAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider"
 *     }
 *   },
 *   admin_permission = "administer sites",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "domain"
 *   },
 *   links = {
 *     "canonical" = "/hosting/sites/{hosting_site}",
 *     "add-form" = "/hosting/sites/add",
 *     "edit-form" = "/hosting/sites/{hosting_site}/edit",
 *     "delete-form" = "/hosting/sites/{hosting_site}/delete",
 *     "collection" = "/hosting/sites"
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
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['client'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Client'))
      ->setSetting('target_type', 'hosting_client')
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['platform'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Platform'))
      ->setSetting('target_type', 'hosting_platform')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['db_server'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Database server'))
      ->setSetting('target_type', 'hosting_server')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['db_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Database name'))
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['profile'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Profile'))
      ->setSetting('target_type', 'hosting_package')
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['language'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Language'))
      ->setDefaultValue('en')
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE);

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
