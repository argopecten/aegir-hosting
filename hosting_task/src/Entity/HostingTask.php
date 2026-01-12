<?php

namespace Drupal\hosting_task\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Hosting task entity.
 *
 * @ContentEntityType(
 *   id = "hosting_task",
 *   label = @Translation("Hosting task"),
 *   base_table = "hosting_task",
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
 *   admin_permission = "administer tasks",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label"
 *   },
 *   links = {
 *     "canonical" = "/admin/hosting/tasks/{hosting_task}",
 *     "add-form" = "/admin/hosting/tasks/add",
 *     "edit-form" = "/admin/hosting/tasks/{hosting_task}/edit",
 *     "delete-form" = "/admin/hosting/tasks/{hosting_task}/delete",
 *     "collection" = "/admin/hosting/tasks"
 *   }
 * )
 */
class HostingTask extends ContentEntityBase implements HostingTaskInterface {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE);

    $fields['task_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Task type'))
      ->setRequired(TRUE);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Status'))
      ->setRequired(TRUE)
      ->setDefaultValue('queued');

    $fields['context_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Context name'))
      ->setRequired(TRUE);

    $fields['command'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Command'))
      ->setRequired(TRUE);

    $fields['args'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Arguments'))
      ->setDefaultValue('[]');

    $fields['options'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Options'))
      ->setDefaultValue('[]');

    $fields['started'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Started'))
      ->setDefaultValue(0);

    $fields['completed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Completed'))
      ->setDefaultValue(0);

    return $fields;
  }

  public function getTaskType(): string {
    return (string) $this->get('task_type')->value;
  }

  public function getStatus(): string {
    return (string) $this->get('status')->value;
  }

  public function setStatus(string $status): HostingTaskInterface {
    $this->set('status', $status);
    return $this;
  }

  public function getCommand(): string {
    return (string) $this->get('command')->value;
  }

  public function getArgs(): array {
    $raw = (string) $this->get('args')->value;
    return json_decode($raw, TRUE) ?: [];
  }

  public function getOptions(): array {
    $raw = (string) $this->get('options')->value;
    return json_decode($raw, TRUE) ?: [];
  }

}
