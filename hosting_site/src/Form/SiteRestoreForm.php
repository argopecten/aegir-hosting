<?php

namespace Drupal\hosting_site\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;
use Drupal\hosting_task\Service\TaskManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for the Restore site task.
 *
 * Presents a radio list of existing backups for the site, sorted by date.
 */
class SiteRestoreForm extends HostingTaskConfirmFormBase {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(TaskManagerInterface $taskManager, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($taskManager);
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('hosting.task_manager'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hosting_site_restore_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaskType(): string {
    return 'restore';
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
    return $this->t('This will restore the site from the selected backup. The current state will be backed up first.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $hosting_site = NULL, $hosting_platform = NULL, $hosting_server = NULL): array {
    $form = parent::buildForm($form, $form_state, $hosting_site, $hosting_platform, $hosting_server);

    // Load backups for this site.
    $backup_ids = $this->entityTypeManager
      ->getStorage('hosting_site_backup')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('site', $this->hostingEntity->id())
      ->sort('id', 'DESC')
      ->execute();

    $backups = $this->entityTypeManager
      ->getStorage('hosting_site_backup')
      ->loadMultiple($backup_ids);

    if (empty($backups)) {
      $this->messenger()->addWarning($this->t('No backups are available for this site.'));
      $form['actions']['submit']['#access'] = FALSE;
      return $form;
    }

    $options = [];
    $date_formatter = \Drupal::service('date.formatter');
    foreach ($backups as $backup) {
      $created = $backup->get('created')->value ?? $backup->id();
      $filename = $backup->get('filename')->value ?? '';
      $description = $backup->get('description')->value ?? $this->t('Generated on request');
      $date = is_numeric($created) ? $date_formatter->format($created, 'medium') : $created;
      $options[$backup->id()] = $this->t('@date — @description (@file)', [
        '@date' => $date,
        '@description' => $description,
        '@file' => basename($filename),
      ]);
    }

    $form['backup_id'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select a backup to restore'),
      '#options' => $options,
      '#required' => TRUE,
      '#weight' => -10,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaskOptions(FormStateInterface $form_state): array {
    $options = parent::getTaskOptions($form_state);
    $options['backup_id'] = $form_state->getValue('backup_id');
    return $options;
  }

}
