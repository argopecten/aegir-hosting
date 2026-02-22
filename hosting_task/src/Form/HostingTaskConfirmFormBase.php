<?php

namespace Drupal\hosting_task\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hosting_task\Service\TaskManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for hosting task confirmation forms.
 *
 * Subclasses must define getTaskType() and may override
 * getEntityTypeId(), getRouteEntityKey(), getTaskOptions(), etc.
 */
abstract class HostingTaskConfirmFormBase extends ConfirmFormBase {

  protected TaskManagerInterface $taskManager;

  /**
   * The hosting entity this task acts upon.
   */
  protected ContentEntityInterface $hostingEntity;

  public function __construct(TaskManagerInterface $taskManager) {
    $this->taskManager = $taskManager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('hosting.task_manager'),
    );
  }

  /**
   * The task type string (e.g. 'verify', 'backup', 'disable').
   */
  abstract protected function getTaskType(): string;

  /**
   * The entity type ID this form operates on.
   */
  protected function getEntityTypeId(): string {
    return 'hosting_site';
  }

  /**
   * The route parameter name for the entity.
   */
  protected function getRouteEntityKey(): string {
    return $this->getEntityTypeId();
  }

  /**
   * Return the context name used for task creation.
   */
  protected function getContextName(): string {
    $entity = $this->hostingEntity;
    // Sites use domain, platforms use name, servers use hostname.
    $label_key = $entity->getEntityType()->getKey('label');
    return $entity->get($label_key)->value;
  }

  /**
   * Additional task options. Override in subclasses.
   */
  protected function getTaskOptions(FormStateInterface $form_state): array {
    return [];
  }

  /**
   * Additional task arguments. Override in subclasses.
   */
  protected function getTaskArgs(FormStateInterface $form_state): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to @task @entity?', [
      '@task' => $this->getTaskType(),
      '@entity' => $this->hostingEntity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return $this->hostingEntity->toUrl('canonical');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $hosting_site = NULL, $hosting_platform = NULL, $hosting_server = NULL): array {
    // Resolve the entity from route parameters.
    $entity = $hosting_site ?? $hosting_platform ?? $hosting_server;
    if (is_numeric($entity)) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage($this->getEntityTypeId())
        ->load($entity);
    }
    $this->hostingEntity = $entity;

    $form = parent::buildForm($form, $form_state);

    // Support rendering in a modal dialog.
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $context_name = $this->getContextName();
    $task_type = $this->getTaskType();
    $args = $this->getTaskArgs($form_state);
    $options = $this->getTaskOptions($form_state);

    $this->taskManager->createTask($context_name, $task_type, $args, $options);

    $this->messenger()->addStatus($this->t('@task task has been queued for @entity.', [
      '@task' => ucfirst($task_type),
      '@entity' => $this->hostingEntity->label(),
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
