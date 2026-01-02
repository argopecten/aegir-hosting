<?php

namespace Drupal\hosting_client\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextfieldWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a machine name widget for hosting client unix names.
 *
 * @FieldWidget(
 *   id = "hosting_client_uname",
 *   label = @Translation("Hosting client machine name"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class HostingClientUnameWidget extends StringTextfieldWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $config = \Drupal::config('hosting_client.settings');
    $prefix = $config->get('hosting_client_prefix') ?? 'no prefix define';

    $element['value']['#type'] = 'machine_name';
    $element['value']['#access'] = \Drupal::currentUser()->hasPermission('edit client uname');
    $element['value']['#size'] = HOSTING_CLIENT_MAX_GROUP_LENGTH;
    $element['value']['#maxlength'] = HOSTING_CLIENT_MAX_GROUP_LENGTH;
    $element['value']['#description'] = $this->t('A machine-usable name that can be used internally, for example to map to a UNIX group in the backend. It is unique accross the system. If no value is provided, it is deduced from the client name, by stripping spaces and metacharacters and adding a prefix (%prefix).', ['%prefix' => $prefix]);
    $element['value']['#machine_name'] = [
      'exists' => 'hosting_get_client_by_uname',
      'source' => ['label', 0, 'value'],
      'label' => $this->t('Internal name'),
      'replace_pattern' => '[^a-z0-9_-]+',
      'replace' => '_',
    ];

    return $element;
  }

}
