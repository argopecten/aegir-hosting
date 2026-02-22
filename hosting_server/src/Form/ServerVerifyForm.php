<?php

namespace Drupal\hosting_server\Form;

use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;

/**
 * Confirmation form for the Verify server task.
 */
class ServerVerifyForm extends HostingTaskConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hosting_server_verify_form';
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
    return 'hosting_server';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will verify the server configuration, check service connectivity, and regenerate configuration files.');
  }

}
