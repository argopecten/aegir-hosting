<?php

namespace Drupal\hosting_site\Form;

use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;

/**
 * Confirmation form for the Verify site task.
 */
class SiteVerifyForm extends HostingTaskConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hosting_site_verify_form';
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
    return 'hosting_site';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will verify the site configuration, re-generate settings.php, vhost and restart the web server.');
  }

}
