<?php

namespace Drupal\hosting_site\Form;

use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;

/**
 * Confirmation form for the Reset Password site task.
 *
 * Generates a one-time login URL for the admin user (uid 1) of the site.
 */
class SiteResetPasswordForm extends HostingTaskConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hosting_site_reset_password_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaskType(): string {
    return 'login-reset';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityTypeId(): string {
    return 'hosting_site';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Generate a one-time login link for %site?', [
      '%site' => $this->hostingEntity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will generate a new one-time login URL for the admin account of this site.');
  }

}
