<?php

namespace Drupal\hosting_queued\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class HostingQueuedSemaphoreForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hosting_queued_remove_semaphore_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#title'] = $this->t('Unlock Hosting Queue');

    $processing_tasks = \Drupal::database()->select('hosting_task', 't')
      ->fields('t', ['id'])
      ->condition('task_status', HOSTING_TASK_PROCESSING)
      ->execute()
      ->fetchField();
    $semaphore_status = \Drupal::database()->select('semaphore', 's')
      ->fields('s', ['name'])
      ->condition('s.name', 'hosting_queue_tasks_running')
      ->execute()
      ->fetchField();

    $form['hosting_queue_tasks_running'] = [
      '#type' => 'item',
      '#title' => $this->t('Tasks Running'),
      '#markup' => $processing_tasks ? $this->t('Yes') : $this->t('No'),
    ];

    $form['hosting_queue_tasks_locked'] = [
      '#type' => 'item',
      '#title' => $this->t('Task Queue Locked'),
      '#markup' => $semaphore_status ? $this->t('Yes') : $this->t('No'),
    ];

    $disabled = TRUE;
    $button_class = '';
    if ($semaphore_status && !$processing_tasks) {
      $form['hosting_queue_tasks_locked']['#description'] = $this->t('The task queue is locked but there are no tasks running. You should unlock the queue.');
      $button_class = 'btn-success';
      $disabled = FALSE;
    }
    else {
      $form['hosting_queue_tasks_locked']['#description'] = $this->t('The task queue is not locked.');
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Unlock Hosting Queue'),
      '#disabled' => $disabled,
      '#attributes' => [
        'class' => [$button_class],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    global $locks;
    unset($locks['hosting_queue_tasks_running']);
    $success = \Drupal::database()->delete('semaphore')
      ->condition('name', 'hosting_queue_tasks_running')
      ->execute();

    if ($success) {
      $this->messenger()->addMessage($this->t('Hosting Queue unlocked.'));
    }
    else {
      $this->messenger()->addError($this->t('Hosting Queue was not unlocked. Something went wrong.'));
    }
  }

}
