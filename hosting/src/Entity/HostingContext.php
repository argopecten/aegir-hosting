<?php

namespace Drupal\hosting\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Hosting context entity.
 *
 * @ContentEntityType(
 *   id = "hosting_context",
 *   label = @Translation("Hosting context"),
 *   base_table = "hosting_context",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "context_name"
 *   },
 *   admin_permission = "administer hosting"
 * )
 */
class HostingContext extends ContentEntityBase implements HostingContextInterface {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['context_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Context name'))
      ->setRequired(TRUE);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setRequired(TRUE);

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setRequired(TRUE);

    return $fields;
  }

  public function getContextName(): string {
    return (string) $this->get('context_name')->value;
  }

  public function getEntityTypeId(): string {
    return (string) $this->get('entity_type')->value;
  }

  public function getEntityId(): int {
    return (int) $this->get('entity_id')->value;
  }

}
