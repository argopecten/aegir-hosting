<?php

namespace Drupal\hosting_platform\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\hosting_platform\Entity\HostingPlatform;
use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;

/**
 * Confirmation form for the Lock platform task.
 *
 * Locking a platform prevents new sites from being installed on it.
 */
class PlatformLockForm extends HostingTaskConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hosting_platform_lock_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaskType(): string {
    return 'lock';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityTypeId(): string {
    return 'hosting_platform';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Locking this platform will prevent new sites from being created on it. Existing sites will not be affected.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $hosting_site = NULL, $hosting_platform = NULL, $hosting_server = NULL): array {
    $form = parent::buildForm($form, $form_state, $hosting_site, $hosting_platform, $hosting_server);

    $status = (int) $this->hostingEntity->get('status')->value;
    if ($status === HostingPlatform::STATUS_LOCKED) {
      $this->messenger()->addWarning($this->t('This platform is already locked.'));
      $form['actions']['submit']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Lock is a frontend-only status change, no backend task needed.
    $this->hostingEntity->set('status', HostingPlatform::STATUS_LOCKED);
    $this->hostingEntity->save();
    $this->messenger()->addStatus($this->t('Platform %platform has been locked.', [
      '%platform' => $this->hostingEntity->label(),
    ]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
