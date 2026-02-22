<?php

namespace Drupal\hosting_site\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\hosting_site\Entity\HostingSite;
use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;

/**
 * Confirmation form for the Delete site task.
 *
 * In the default Aegir configuration, a site must be disabled before it can
 * be deleted. This can be changed via the hosting settings.
 */
class SiteDeleteTaskForm extends HostingTaskConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hosting_site_delete_task_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaskType(): string {
    return 'delete';
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
    return $this->t('Are you sure you want to permanently delete the site %site?', [
      '%site' => $this->hostingEntity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will permanently remove the site, its database, and all associated files. A final backup will be made before deletion. This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $hosting_site = NULL, $hosting_platform = NULL, $hosting_server = NULL): array {
    $form = parent::buildForm($form, $form_state, $hosting_site, $hosting_platform, $hosting_server);

    $status = (int) $this->hostingEntity->get('status')->value;
    $require_disable = \Drupal::config('hosting.settings')->get('require_disable_before_delete') ?? TRUE;

    if ($require_disable && $status !== HostingSite::STATUS_DISABLED) {
      $this->messenger()->addWarning($this->t('You must disable this site before deleting it. This can be changed in <a href=":url">hosting settings</a>.', [
        ':url' => '/hosting/settings',
      ]));
      $form['actions']['submit']['#access'] = FALSE;
    }

    return $form;
  }

}
