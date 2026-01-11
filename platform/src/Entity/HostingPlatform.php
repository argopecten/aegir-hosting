<?php

namespace Drupal\hosting_platform\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\hosting\Entity\HostingEntityBase;
use Drupal\hosting_platform\Plugin\Field\HostingPlatformClientsItemList;

/**
 * Defines the Hosting platform entity.
 *
 * @ContentEntityType(
 *   id = "hosting_platform",
 *   label = @Translation("Hosting platform"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\hosting_platform\HostingPlatformListBuilder",
 *     "access" = "Drupal\hosting_platform\HostingPlatformAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\hosting_platform\Form\HostingPlatformForm",
 *       "edit" = "Drupal\hosting_platform\Form\HostingPlatformForm",
 *       "delete" = "Drupal\hosting_platform\Form\HostingPlatformDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "hosting_platform",
 *   admin_permission = "administer platforms",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label"
 *   },
 *   links = {
 *     "canonical" = "/hosting/platform/{hosting_platform}",
 *     "add-form" = "/hosting/platforms/add",
 *     "edit-form" = "/hosting/platform/{hosting_platform}/edit",
 *     "delete-form" = "/hosting/platform/{hosting_platform}/delete",
 *     "collection" = "/hosting/platforms"
 *   }
 * )
 */
class HostingPlatform extends HostingEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label']
      ->setLabel(t('Platform name'))
      ->setDescription(t('Choose a unique descriptive name for your platform, for example "Drupal 11.0".'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['deployment_method'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Deployment method'))
      ->setDescription(t('Choose how to deploy this platform.'))
      ->setSetting('allowed_values', [
        'manual' => 'Manual deployment',
        'composer' => 'Composer create-project',
      ])
      ->setDefaultValue('manual')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['repository_url'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Repository URL'))
      ->setDescription(t('The Composer repository URL (e.g., drupal/recommended-project or git repository URL with composer.json).'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['publish_path'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Publish path'))
      ->setDescription(t('The absolute path where sites will be hosted. This should exist and be unique across all servers.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['web_server'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Web server'))
      ->setDescription(t('The web server the sites will be hosted on.'))
      ->setSetting('target_type', 'hosting_server')
      ->setSetting('handler', 'default:hosting_server:web')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['php_version'] = BaseFieldDefinition::create('string')
      ->setLabel(t('PHP version'))
      ->setDescription(t('The PHP version used by this platform (e.g., 8.3, 8.2, 8.1).'))
      ->setSetting('max_length', 10)
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['verified'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Verified'))
      ->setDescription(t('The last time this platform was verified.'))
      ->setDefaultValue(0)
      ->setDisplayConfigurable('view', TRUE);

    $fields['clients'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Clients'))
      ->setSetting('target_type', 'hosting_client')
      ->setComputed(TRUE)
      ->setClass(HostingPlatformClientsItemList::class)
      ->setReadOnly(TRUE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED);

    return $fields;
  }

}
