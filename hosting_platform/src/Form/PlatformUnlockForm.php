<?php

namespace Drupal\hosting_platform\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\hosting_platform\Entity\HostingPlatform;
use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;

/**
 * Confirmation form for the Unlock platform task.
 */
class PlatformUnlockForm extends HostingTaskConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hosting_platform_unlock_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaskType(): string {
    return 'unlock';
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
    return $this->t('Unlocking this platform will allow new sites to be installed on it again.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $hosting_site = NULL, $hosting_platform = NULL, $hosting_server = NULL): array {
    $form = parent::buildForm($form, $form_state, $hosting_site, $hosting_platform, $hosting_server);

    $status = (int) $this->hostingEntity->get('status')->value;
    if ($status !== HostingPlatform::STATUS_LOCKED) {
      $this->messenger()->addWarning($this->t('This platform is not currently locked.'));
      $form['actions']['submit']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Unlock is a frontend-only status change, no backend task needed.
    $this->hostingEntity->set('status', HostingPlatform::STATUS_ENABLED);
    $this->hostingEntity->save();
    $this->messenger()->addStatus($this->t('Platform %platform has been unlocked.', [
      '%platform' => $this->hostingEntity->label(),
    ]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
