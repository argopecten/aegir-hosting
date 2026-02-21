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
 *     "view_builder" = "Drupal\hosting_task\Entity\HostingTaskViewBuilder",
 *     "list_builder" = "Drupal\hosting_task\Entity\HostingTaskListBuilder",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "add" = "Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider"
 *     }
 *   },
 *   admin_permission = "administer tasks",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label"
 *   },
 *   links = {
 *     "canonical" = "/hosting/tasks/{hosting_task}",
 *     "add-form" = "/hosting/tasks/add",
 *     "edit-form" = "/hosting/tasks/{hosting_task}/edit",
 *     "delete-form" = "/hosting/tasks/{hosting_task}/delete",
 *     "collection" = "/hosting/tasks"
 *   }
 * )
 */
class HostingTask extends ContentEntityBase implements HostingTaskInterface {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['task_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Task type'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Status'))
      ->setRequired(TRUE)
      ->setDefaultValue('queued')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['context_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Context name'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['command'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Command'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['args'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Arguments'))
      ->setDefaultValue('[]')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['options'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Options'))
      ->setDefaultValue('[]')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['started'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Started'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['completed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Completed'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 8,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['retry_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Retry count'))
      ->setDescription(t('Number of times this task has been retried'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 9,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['max_retries'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Max retries'))
      ->setDescription(t('Maximum number of retry attempts'))
      ->setDefaultValue(3)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['next_retry_time'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Next retry time'))
      ->setDescription(t('Timestamp when task should be retried'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 11,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['cancelled_by'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Cancelled by'))
      ->setDescription(t('User who cancelled this task'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 12,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['process_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Process ID'))
      ->setDescription(t('PID of the running backend process'))
      ->setDefaultValue(0);

    $fields['metadata'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Metadata'))
      ->setDescription(t('JSON-encoded task metadata (e.g., chain_next for task chaining)'))
      ->setDefaultValue('{}');

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
