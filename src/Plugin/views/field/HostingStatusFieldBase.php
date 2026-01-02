<?php

namespace Drupal\hosting\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * Base handler for hosting status fields with display mode options.
 */
abstract class HostingStatusFieldBase extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['status_mode'] = ['default' => 'text'];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['status_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display mode'),
      '#options' => [
        'text' => $this->t('Text'),
        'image' => $this->t('Image'),
        'text_image' => $this->t('Text & Image'),
        'class' => $this->t('CSS class'),
      ],
      '#default_value' => $this->options['status_mode'] ?? 'text',
      '#description' => $this->t('Display mode of status values.'),
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * Build output for the configured status display mode.
   */
  protected function buildStatusOutput($output, $class_value = '') {
    $output = $this->sanitizeValue($output);

    switch ($this->options['status_mode']) {
      case 'image':
        return Markup::create("<span class='hosting-status hosting-status-icon'></span>");

      case 'text_image':
        return Markup::create("<span class='hosting-status'>{$output}</span>");

      case 'class':
        return (string) $class_value;
    }

    return $output;
  }

}
