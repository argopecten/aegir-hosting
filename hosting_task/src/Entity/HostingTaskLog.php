<?php

namespace Drupal\hosting_task\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Hosting task log entity.
 *
 * @ContentEntityType(
 *   id = "hosting_task_log",
 *   label = @Translation("Hosting task log"),
 *   base_table = "hosting_task_log",
 *   handlers = {
 *     "list_builder" = "Drupal\\Core\\Entity\\EntityListBuilder",
 *     "form" = {
 *       "default" = "Drupal\\Core\\Entity\\ContentEntityForm",
 *       "add" = "Drupal\\Core\\Entity\\ContentEntityForm",
 *       "edit" = "Drupal\\Core\\Entity\\ContentEntityForm",
 *       "delete" = "Drupal\\Core\\Entity\\ContentEntityDeleteForm"
 *     },
 *     "access" = "Drupal\\Core\\Entity\\EntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\\Core\\Entity\\Routing\\AdminHtmlRouteProvider"
 *     }
 *   },
 *   admin_permission = "view task logs",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "message"
 *   },
 *   links = {
 *     "collection" = "/admin/hosting/task-logs"
 *   }
 * )
 */
class HostingTaskLog extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['task'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Task'))
      ->setSetting('target_type', 'hosting_task')
      ->setRequired(TRUE);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type'))
      ->setRequired(TRUE)
      ->setDefaultValue('status');

    $fields['message'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Message'))
      ->setRequired(TRUE);

    $fields['error'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Error'))
      ->setDefaultValue('');

    $fields['timestamp'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Timestamp'))
      ->setDefaultValue(0);

    return $fields;
  }

}
