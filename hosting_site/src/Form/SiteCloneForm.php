<?php

namespace Drupal\hosting_site\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;
use Drupal\hosting_task\Service\TaskManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for the Clone site task.
 *
 * Allows the user to specify a new domain name, target platform, and
 * database server for the cloned site. Mirrors D7's clone form behavior.
 */
class SiteCloneForm extends HostingTaskConfirmFormBase {

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
    return 'hosting_site_clone_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaskType(): string {
    return 'clone';
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
    return $this->t('Clone site %site', [
      '%site' => $this->hostingEntity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Create a complete copy of this site with a new domain name.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $hosting_site = NULL, $hosting_platform = NULL, $hosting_server = NULL): array {
    $form = parent::buildForm($form, $form_state, $hosting_site, $hosting_platform, $hosting_server);

    $form['new_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('New domain name'),
      '#description' => $this->t('The domain name for the cloned site. Must be unique.'),
      '#required' => TRUE,
      '#weight' => -20,
    ];

    // Load available platforms.
    $platform_ids = $this->entityTypeManager
      ->getStorage('hosting_platform')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->sort('name')
      ->execute();

    $platforms = $this->entityTypeManager
      ->getStorage('hosting_platform')
      ->loadMultiple($platform_ids);

    $current_platform_id = $this->hostingEntity->get('platform')->target_id;
    $platform_options = [];
    foreach ($platforms as $platform) {
      $label = $platform->label();
      if ((int) $platform->id() === (int) $current_platform_id) {
        $label .= ' ' . $this->t('(current)');
      }
      $platform_options[$platform->id()] = $label;
    }

    $form['target_platform'] = [
      '#type' => 'radios',
      '#title' => $this->t('Target platform'),
      '#options' => $platform_options,
      '#default_value' => $current_platform_id,
      '#required' => TRUE,
      '#weight' => -15,
    ];

    // Load available database servers.
    $server_ids = $this->entityTypeManager
      ->getStorage('hosting_server')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->sort('hostname')
      ->execute();

    $servers = $this->entityTypeManager
      ->getStorage('hosting_server')
      ->loadMultiple($server_ids);

    if (count($servers) > 1) {
      $server_options = [];
      foreach ($servers as $server) {
        $server_options[$server->id()] = $server->label();
      }
      $form['db_server'] = [
        '#type' => 'select',
        '#title' => $this->t('Database server'),
        '#options' => $server_options,
        '#default_value' => $this->hostingEntity->get('db_server')->target_id ?? NULL,
        '#weight' => -10,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $new_domain = trim($form_state->getValue('new_domain'));
    if ($new_domain) {
      // Check uniqueness.
      $existing = $this->entityTypeManager
        ->getStorage('hosting_site')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('domain', $new_domain)
        ->range(0, 1)
        ->count()
        ->execute();
      if ($existing > 0) {
        $form_state->setErrorByName('new_domain', $this->t('The domain %domain is already in use.', [
          '%domain' => $new_domain,
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaskOptions(FormStateInterface $form_state): array {
    $options = parent::getTaskOptions($form_state);
    $options['new_domain'] = trim($form_state->getValue('new_domain'));
    $options['target_platform'] = $form_state->getValue('target_platform');
    if ($form_state->getValue('db_server')) {
      $options['db_server'] = $form_state->getValue('db_server');
    }
    return $options;
  }

}
