<?php

namespace Drupal\hosting_site\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;

/**
 * Form for the Backup site task.
 *
 * Provides an optional description field so the user can annotate what
 * the backup is for (matching D7 behavior).
 */
class SiteBackupForm extends HostingTaskConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hosting_site_backup_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaskType(): string {
    return 'backup';
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
    return $this->t('This will create a complete backup of the site, including files and database.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $hosting_site = NULL, $hosting_platform = NULL, $hosting_server = NULL): array {
    $form = parent::buildForm($form, $form_state, $hosting_site, $hosting_platform, $hosting_server);

    $form['description_field'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Optionally describe the reason for this backup.'),
      '#rows' => 3,
      '#weight' => -10,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaskOptions(FormStateInterface $form_state): array {
    $options = parent::getTaskOptions($form_state);
    $description = $form_state->getValue('description_field');
    if ($description) {
      $options['description'] = $description;
    }
    return $options;
  }

}
