<?php

namespace Drupal\hosting_site\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;
use Drupal\hosting_task\Service\TaskManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for the Migrate site task.
 *
 * Allows the user to optionally rename the site and select a target platform.
 * Mirrors D7's migrate form with platform comparison support.
 */
class SiteMigrateForm extends HostingTaskConfirmFormBase {

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
    return 'hosting_site_migrate_form';
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
    return 'hosting_site';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Migrate site %site', [
      '%site' => $this->hostingEntity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Migrate (upgrade) this site to a new platform, or rename it. A backup will be made automatically before the migration begins.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $hosting_site = NULL, $hosting_platform = NULL, $hosting_server = NULL): array {
    $form = parent::buildForm($form, $form_state, $hosting_site, $hosting_platform, $hosting_server);

    $current_domain = $this->hostingEntity->get('domain')->value;
    $current_platform_id = $this->hostingEntity->get('platform')->target_id;

    $form['new_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Domain name'),
      '#default_value' => $current_domain,
      '#description' => $this->t('Change the domain to rename the site, or leave as-is to keep the current name.'),
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

    $platform_options = [];
    foreach ($platforms as $platform) {
      $label = $platform->label();
      if ((int) $platform->id() === (int) $current_platform_id) {
        $label .= ' ' . $this->t('(current platform)');
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
      '#description' => $this->t('Select the platform to migrate this site to. If a platform has package incompatibilities, it will show warnings.'),
    ];

    // Add "Compare platforms" link for each platform option.
    foreach ($platforms as $platform) {
      if ((int) $platform->id() !== (int) $current_platform_id) {
        $compare_url = Url::fromRoute('hosting_platform.compare', [
          'source' => $current_platform_id,
          'target' => $platform->id(),
        ]);
        $form['compare_' . $platform->id()] = [
          '#type' => 'link',
          '#title' => $this->t('Compare with @platform', ['@platform' => $platform->label()]),
          '#url' => $compare_url,
          '#attributes' => [
            'class' => ['use-ajax', 'hosting-compare-link'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(['width' => 800]),
          ],
          '#weight' => -14,
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $new_domain = trim($form_state->getValue('new_domain'));
    $current_domain = $this->hostingEntity->get('domain')->value;

    // If domain changed, check uniqueness.
    if ($new_domain && $new_domain !== $current_domain) {
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
    return $options;
  }

}
