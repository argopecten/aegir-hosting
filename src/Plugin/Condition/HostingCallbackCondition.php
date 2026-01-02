<?php

namespace Drupal\hosting\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a callback-based visibility condition for hosting blocks.
 *
 * @Condition(
 *   id = "hosting_callback_visibility",
 *   label = @Translation("Hosting callback visibility")
 * )
 */
class HostingCallbackCondition extends ConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['callback' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['callback'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Callback function'),
      '#default_value' => $this->configuration['callback'],
      '#description' => $this->t('Callable function name used to determine visibility.'),
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['callback'] = $form_state->getValue('callback');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    if (empty($this->configuration['callback'])) {
      return $this->isNegated();
    }

    $callback = $this->configuration['callback'];
    $result = is_callable($callback) ? (bool) call_user_func($callback) : FALSE;

    return $this->isNegated() ? !$result : $result;
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $callback = $this->configuration['callback'] ?: $this->t('None');
    return $this->t('Callback: @callback', ['@callback' => $callback]);
  }

}
