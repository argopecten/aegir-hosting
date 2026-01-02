<?php

namespace Drupal\hosting_queued\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class HostingQueuedSettingsForm extends ConfigFormBase {

  public function getFormId() {
    return 'hosting_queued_settings_form';
  }

  protected function getEditableConfigNames() {
    return ['hosting_queued.settings'];
  }

  protected function getSetting($key, $default = NULL) {
    $config_value = $this->config('hosting_queued.settings')->get($key);
    return \Drupal::state()->get($key, $config_value !== NULL ? $config_value : $default);
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Note that the settings on this form will only apply to the daemon once it has been restarted, which by default happens as least once an hour.'),
      '#weight' => -100,
    ];

    $semaphore_status = \Drupal::database()->select('semaphore', 's')
      ->fields('s', ['name'])
      ->condition('s.name', 'hosting_queue_tasks_running')
      ->execute()
      ->fetchField();

    $processing_tasks = \Drupal::database()->select('hosting_task', 't')
      ->fields('t', ['id'])
      ->condition('task_status', HOSTING_TASK_PROCESSING)
      ->execute()
      ->fetchField();

    $form['hosting_queue_tasks_running'] = [
      '#type' => 'item',
      '#title' => $this->t('Tasks Running'),
      '#markup' => $processing_tasks ? $this->t('Yes') : $this->t('No'),
    ];

    if (!$processing_tasks && $semaphore_status) {
      $form['hosting_queue_tasks_running']['#description'] = $this->t('Warning: The task queue is locked but there are no tasks running. !link to remove the lock.', [
        '!link' => \Drupal\Core\Link::fromTextAndUrl($this->t('Click Here'), \Drupal\Core\Url::fromUserInput('/admin/hosting/queued/semaphore'))->toString(),
      ]);
    }

    $last_seen = \Drupal::state()->get('hosting_queued_process_started');
    $form['hosting_queued_process_started'] = [
      '#type' => 'item',
      '#title' => $this->t('Runner status'),
      '#markup' => !empty($last_seen) ? $this->t('Last started: @interval ago.', ['@interval' => format_interval(REQUEST_TIME - $last_seen)]) : $this->t('Never started.'),
    ];

    $delay_values = range(1, 60);
    $delay_options = array_combine($delay_values, array_map([$this, 'formatDelayOption'], $delay_values));
    $lifetime_values = [
      60,
      60 * 5,
      60 * 10,
      60 * 15,
      60 * 20,
      60 * 25,
      60 * 30,
      60 * 35,
      60 * 40,
      60 * 45,
      60 * 50,
      60 * 55,
      60 * 60,
    ];
    $lifetime_options = array_combine($lifetime_values, array_map('format_interval', $lifetime_values));

    $form['hosting_queued_post_task_delay'] = [
      '#type' => 'select',
      '#title' => $this->t('Post task delay'),
      '#description' => $this->t('Tasks are executed as fast as possible, so you may wish to add a delay after the execution of each task. After this delay, new tasks will still start executing almost instantly.'),
      '#default_value' => $this->getSetting('hosting_queued_post_task_delay', 0),
      '#options' => [
        0 => $this->t('No delay'),
      ] + $delay_options,
    ];

    $form['hosting_queued_process_lifetime'] = [
      '#type' => 'select',
      '#title' => $this->t('Process lifetime timeout'),
      '#description' => $this->t('Because of memory leaks and bugs in PHP, the daemon automatically stops after this delay, and is restarted. If you are running a lot of tasks, you may want to lower this so it gets restarted more often. In empirical tests, it was found that around 100KB are leaked for every task fired.'),
      '#default_value' => $this->getSetting('hosting_queued_process_lifetime', 3600),
      '#options' => $lifetime_options,
    ];

    $form['hosting_queued_paused'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pause the Hosting Queue'),
      '#description' => $this->t('Temporarily pause the hosting queue from running tasks.'),
      '#default_value' => $this->getSetting('hosting_queued_paused', 0),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('hosting_queued.settings');
    $keys = [
      'hosting_queued_post_task_delay',
      'hosting_queued_process_lifetime',
      'hosting_queued_paused',
    ];

    foreach ($keys as $key) {
      $value = $form_state->getValue($key);
      $config->set($key, $value);
      \Drupal::state()->set($key, $value);
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Format delay options in seconds.
   */
  private function formatDelayOption($value) {
    return \Drupal::translation()->formatPlural($value, '1 second', '@count seconds');
  }

}
