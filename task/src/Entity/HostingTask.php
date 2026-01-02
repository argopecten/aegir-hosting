<?php

namespace Drupal\hosting_task\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\hosting\Entity\HostingEntityBase;

/**
 * Defines the Hosting task entity.
 *
 * @ContentEntityType(
 *   id = "hosting_task",
 *   label = @Translation("Hosting task"),
 *   handlers = {
 *     "list_builder" = "Drupal\hosting_task\HostingTaskListBuilder",
 *     "access" = "Drupal\hosting_task\HostingTaskAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\hosting_task\Form\HostingTaskForm",
 *       "edit" = "Drupal\hosting_task\Form\HostingTaskForm",
 *       "delete" = "Drupal\hosting_task\Form\HostingTaskDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "hosting_task",
 *   admin_permission = "administer tasks",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label"
 *   },
 *   links = {
 *     "canonical" = "/hosting/task/{hosting_task}",
 *     "add-form" = "/hosting/tasks/add",
 *     "edit-form" = "/hosting/task/{hosting_task}/edit",
 *     "delete-form" = "/hosting/task/{hosting_task}/delete",
 *     "collection" = "/hosting/tasks"
 *   }
 * )
 */
class HostingTask extends HostingEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label']->setLabel(t('Task'));

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('Drupal\\node\\Entity\\Node::getCurrentUserId');

    $fields['task_type'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Task type'));

    $fields['rid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Related entity ID'))
      ->setDefaultValue(0);

    $fields['task_status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Task status'))
      ->setDefaultValue(0);

    $fields['executed'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Executed timestamp'))
      ->setDefaultValue(0);

    $fields['delta'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Duration'))
      ->setDefaultValue(0);

    return $fields;
  }

}
