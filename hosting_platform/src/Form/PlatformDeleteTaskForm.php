<?php

namespace Drupal\hosting_platform\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;
use Drupal\hosting_task\Service\TaskManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for the Delete platform task.
 *
 * A platform can only be deleted if no sites are hosted on it.
 */
class PlatformDeleteTaskForm extends HostingTaskConfirmFormBase {

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
    return 'hosting_platform_delete_task_form';
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
    return 'hosting_platform';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to permanently delete the platform %platform?', [
      '%platform' => $this->hostingEntity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will permanently remove the platform and all its files from the server. This action cannot be undone. No backup is made of the platform.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $hosting_site = NULL, $hosting_platform = NULL, $hosting_server = NULL): array {
    $form = parent::buildForm($form, $form_state, $hosting_site, $hosting_platform, $hosting_server);

    // Check if any sites exist on this platform.
    $site_count = $this->entityTypeManager
      ->getStorage('hosting_site')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('platform', $this->hostingEntity->id())
      ->condition('status', -2, '!=')
      ->count()
      ->execute();

    if ($site_count > 0) {
      $this->messenger()->addError($this->t('This platform cannot be deleted because it still has @count site(s). You must migrate or delete all sites first.', [
        '@count' => $site_count,
      ]));
      $form['actions']['submit']['#access'] = FALSE;
    }

    return $form;
  }

}
