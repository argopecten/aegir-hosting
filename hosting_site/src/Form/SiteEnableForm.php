<?php

namespace Drupal\hosting_site\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\hosting_site\Entity\HostingSite;
use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;

/**
 * Confirmation form for the Enable site task.
 */
class SiteEnableForm extends HostingTaskConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hosting_site_enable_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaskType(): string {
    return 'enable';
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
    return $this->t('This will re-enable the disabled site, making it accessible again.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $hosting_site = NULL, $hosting_platform = NULL, $hosting_server = NULL): array {
    $form = parent::buildForm($form, $form_state, $hosting_site, $hosting_platform, $hosting_server);

    // Only disabled sites can be enabled.
    $status = (int) $this->hostingEntity->get('status')->value;
    if ($status !== HostingSite::STATUS_DISABLED) {
      $this->messenger()->addWarning($this->t('This site is not currently disabled.'));
      $form['actions']['submit']['#access'] = FALSE;
    }

    return $form;
  }

}
