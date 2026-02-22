<?php

namespace Drupal\hosting_platform\Form;

use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;

/**
 * Confirmation form for the Verify platform task.
 */
class PlatformVerifyForm extends HostingTaskConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hosting_platform_verify_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaskType(): string {
    return 'verify';
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
    return $this->t('This will verify the platform, scan for packages, update the registry, and regenerate web server configuration.');
  }

}
