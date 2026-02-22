<?php

namespace Drupal\hosting_site\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\hosting_site\Entity\HostingSite;
use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;

/**
 * Confirmation form for the Disable site task.
 */
class SiteDisableForm extends HostingTaskConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hosting_site_disable_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaskType(): string {
    return 'disable';
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
    return $this->t('This will disable the site, making it inaccessible. You can re-enable it later.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $hosting_site = NULL, $hosting_platform = NULL, $hosting_server = NULL): array {
    $form = parent::buildForm($form, $form_state, $hosting_site, $hosting_platform, $hosting_server);

    // Only enabled sites can be disabled.
    $status = (int) $this->hostingEntity->get('status')->value;
    if ($status !== HostingSite::STATUS_ENABLED) {
      $this->messenger()->addWarning($this->t('This site is not currently enabled and cannot be disabled.'));
      $form['actions']['submit']['#access'] = FALSE;
    }

    return $form;
  }

}
