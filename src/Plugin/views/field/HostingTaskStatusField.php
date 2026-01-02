<?php

namespace Drupal\hosting\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Status field for hosting tasks.
 *
 * @ViewsField("hosting_task_status")
 */
class HostingTaskStatusField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['status_mode'] = ['default' => 'raw'];

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
        'raw' => $this->t('Raw value'),
        'text' => $this->t('Text value'),
        'class' => $this->t('CSS class'),
      ],
      '#default_value' => $this->options['status_mode'] ?? 'raw',
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);

    switch ($this->options['status_mode']) {
      case 'text':
        return _hosting_parse_error_code($value);

      case 'class':
        return hosting_task_status_class($value);
    }

    return $value;
  }

}
