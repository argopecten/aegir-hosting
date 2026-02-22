<?php

namespace Drupal\hosting_platform\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;
use Drupal\hosting_task\Service\TaskManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for the Migrate platform task.
 *
 * Migrating a platform triggers Migrate tasks for all sites on it.
 */
class PlatformMigrateForm extends HostingTaskConfirmFormBase {

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
    return 'hosting_platform_migrate_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaskType(): string {
    return 'migrate';
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
  public function getQuestion() {
    return $this->t('Migrate all sites from platform %platform', [
      '%platform' => $this->hostingEntity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will queue Migrate tasks for every site on this platform, moving them to the selected target platform.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $hosting_site = NULL, $hosting_platform = NULL, $hosting_server = NULL): array {
    $form = parent::buildForm($form, $form_state, $hosting_site, $hosting_platform, $hosting_server);

    $current_id = (int) $this->hostingEntity->id();

    // Load other enabled platforms.
    $platform_ids = $this->entityTypeManager
      ->getStorage('hosting_platform')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('id', $current_id, '!=')
      ->sort('name')
      ->execute();

    $platforms = $this->entityTypeManager
      ->getStorage('hosting_platform')
      ->loadMultiple($platform_ids);

    if (empty($platforms)) {
      $this->messenger()->addWarning($this->t('No other enabled platforms are available to migrate to.'));
      $form['actions']['submit']['#access'] = FALSE;
      return $form;
    }

    $options = [];
    foreach ($platforms as $platform) {
      $options[$platform->id()] = $platform->label();
    }

    $form['target_platform'] = [
      '#type' => 'radios',
      '#title' => $this->t('Target platform'),
      '#options' => $options,
      '#required' => TRUE,
      '#weight' => -10,
    ];

    // Show how many sites will be migrated.
    $site_count = $this->entityTypeManager
      ->getStorage('hosting_site')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('platform', $current_id)
      ->condition('status', 1)
      ->count()
      ->execute();

    $form['site_count'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('@count site(s) will be migrated.', ['@count' => $site_count]) . '</p>',
      '#weight' => -20,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $target_platform_id = $form_state->getValue('target_platform');
    $current_id = (int) $this->hostingEntity->id();

    // Load all enabled sites on this platform.
    $site_ids = $this->entityTypeManager
      ->getStorage('hosting_site')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('platform', $current_id)
      ->condition('status', 1)
      ->execute();

    $sites = $this->entityTypeManager
      ->getStorage('hosting_site')
      ->loadMultiple($site_ids);

    $count = 0;
    foreach ($sites as $site) {
      $this->taskManager->createTask($site->get('domain')->value, 'migrate', [], [
        'target_platform' => $target_platform_id,
        'new_domain' => $site->get('domain')->value,
      ]);
      $count++;
    }

    $this->messenger()->addStatus($this->t('Queued migrate tasks for @count site(s).', [
      '@count' => $count,
    ]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
