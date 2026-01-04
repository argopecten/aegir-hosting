<?php

namespace Drupal\hosting_site\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\hosting\Entity\HostingEntityBase;

/**
 * Defines the Hosting site entity.
 *
 * @ContentEntityType(
 *   id = "hosting_site",
 *   label = @Translation("Hosting site"),
 *   handlers = {
 *     "list_builder" = "Drupal\hosting_site\HostingSiteListBuilder",
 *     "access" = "Drupal\hosting_site\HostingSiteAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\hosting_site\Form\HostingSiteForm",
 *       "edit" = "Drupal\hosting_site\Form\HostingSiteForm",
 *       "delete" = "Drupal\hosting_site\Form\HostingSiteDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "hosting_site",
 *   admin_permission = "administer sites",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label"
 *   },
 *   links = {
 *     "canonical" = "/hosting/site/{hosting_site}",
 *     "add-form" = "/hosting/sites/add",
 *     "edit-form" = "/hosting/site/{hosting_site}/edit",
 *     "delete-form" = "/hosting/site/{hosting_site}/delete",
 *     "collection" = "/hosting/sites"
 *   }
 * )
 */
class HostingSite extends HostingEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label']->setLabel(t('Domain name'));
    $fields['label']
      ->setDescription(t('The domain name for this site.'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['client'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Client'))
      ->setDescription(t('The client to whom this site belongs.'))
      ->setSetting('target_type', 'hosting_client')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['platform'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Platform'))
      ->setDescription(t('The Drupal platform you want the site to be hosted on.'))
      ->setSetting('target_type', 'hosting_platform')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['db_server'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Database server'))
      ->setDescription(t('The database server the site will use to host its content.'))
      ->setSetting('target_type', 'hosting_server')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['db_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Database name'))
      ->setSetting('max_length', 64)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['profile'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Install profile'))
      ->setDescription(t('The site profile to install. The platform determines the supported profiles.'))
      ->setSetting('allowed_values_function', 'hosting_site_profile_allowed_values')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['language'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Language'))
      ->setDescription(t('The language of site being installed.'))
      ->setSetting('allowed_values_function', 'hosting_site_language_allowed_values')
      ->setDefaultValue('en')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['last_cron'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Last cron run'))
      ->setDefaultValue(0);

    $fields['cron_key'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Cron key'))
      ->setSetting('max_length', 80);

    $fields['verified'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Verified'))
      ->setDefaultValue(0);

    return $fields;
  }

}
