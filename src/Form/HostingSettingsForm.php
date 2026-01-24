<?php

namespace Drupal\hosting\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HostingSettingsForm extends ConfigFormBase {

  protected EntityTypeManagerInterface $entityTypeManager;

  public static function create(ContainerInterface $container): static {
    /** @var static $instance */
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  public function getFormId(): string {
    return 'hosting_settings_form';
  }

  protected function getEditableConfigNames(): array {
    return ['hosting.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('hosting.settings');

    $form['dispatch_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable queue dispatch'),
      '#default_value' => (bool) $config->get('dispatch_enabled'),
    ];

    $form['backend'] = [
      '#type' => 'details',
      '#title' => $this->t('Backend settings'),
      '#open' => TRUE,
    ];
    $form['backend']['drush_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drush path'),
      '#default_value' => (string) $config->get('backend.drush_path'),
      '#required' => TRUE,
    ];
    $form['backend']['alias'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Backend alias'),
      '#default_value' => (string) $config->get('backend.alias'),
    ];

    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('General settings'),
      '#open' => TRUE,
    ];
    $form['settings']['default_profile'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default profile'),
      '#default_value' => (string) $config->get('settings.default_profile'),
      '#description' => $this->t('Machine name or ID of the default install profile.'),
    ];
    $form['settings']['default_db_server'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Default database server'),
      '#target_type' => 'hosting_server',
      '#default_value' => $config->get('settings.default_db_server') ? $this->entityTypeManager->getStorage('hosting_server')->load($config->get('settings.default_db_server')) : NULL,
    ];
    $form['settings']['ignore_default_profiles'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide platforms with non-default profiles'),
      '#default_value' => (bool) $config->get('settings.ignore_default_profiles'),
    ];
    $form['settings']['blocked_profiles'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Blocked install profiles'),
      '#default_value' => implode("\n", $config->get('settings.blocked_profiles') ?? []),
      '#description' => $this->t('Enter one machine name per line.'),
    ];
    $form['settings']['lock_platforms_by_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Lock platforms by default on initial creation'),
      '#default_value' => (bool) $config->get('settings.lock_platforms_by_default'),
    ];
    $form['settings']['platform_automatic_site_import'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically import sites when verifying platforms'),
      '#default_value' => (bool) $config->get('settings.platform_automatic_site_import'),
    ];
    $form['settings']['require_disable_before_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require site to be disabled before deletion'),
      '#default_value' => (bool) $config->get('settings.require_disable_before_delete'),
    ];
    $form['settings']['delete_force'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force delete for sites, platforms, and servers'),
      '#default_value' => (bool) $config->get('settings.delete_force'),
    ];
    $form['settings']['cron_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Cron method'),
      '#options' => [
        'web' => $this->t('Web-based'),
        'drush' => $this->t('Drush'),
      ],
      '#default_value' => (string) ($config->get('settings.cron_method') ?? 'drush'),
    ];

    $types = [
      'error' => $this->t('error'),
      'warning' => $this->t('warning'),
      'info' => $this->t('info'),
      'message' => $this->t('message'),
      'notice' => $this->t('notice'),
      'ok' => $this->t('ok'),
      'status' => $this->t('status'),
      'success' => $this->t('success'),
    ];
    $form['settings']['task_logs_types_display'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Task log types to display'),
      '#options' => $types,
      '#default_value' => $config->get('settings.task_logs_types_display') ?? array_keys($types),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory->getEditable('hosting.settings')
      ->set('dispatch_enabled', (bool) $form_state->getValue('dispatch_enabled'))
      ->set('backend', [
        'drush_path' => $form_state->getValue(['backend', 'drush_path']),
        'alias' => $form_state->getValue(['backend', 'alias']),
      ])
      ->set('settings', [
        'default_profile' => (string) $form_state->getValue(['settings', 'default_profile']),
        'ignore_default_profiles' => (bool) $form_state->getValue(['settings', 'ignore_default_profiles']),
        'require_disable_before_delete' => (bool) $form_state->getValue(['settings', 'require_disable_before_delete']),
        'delete_force' => (bool) $form_state->getValue(['settings', 'delete_force']),
        'lock_platforms_by_default' => (bool) $form_state->getValue(['settings', 'lock_platforms_by_default']),
        'platform_automatic_site_import' => (bool) $form_state->getValue(['settings', 'platform_automatic_site_import']),
        'cron_method' => (string) $form_state->getValue(['settings', 'cron_method']),
        'default_db_server' => $form_state->getValue(['settings', 'default_db_server']),
        'blocked_profiles' => $this->normalizeList($form_state->getValue(['settings', 'blocked_profiles'])),
        'task_logs_types_display' => array_values(array_filter($form_state->getValue(['settings', 'task_logs_types_display']) ?? [])),
      ])
      ->save();

    parent::submitForm($form, $form_state);
  }

  protected function normalizeList(?string $value): array {
    if (!$value) {
      return [];
    }
    $items = preg_split('/\\r?\\n/', $value) ?: [];
    $items = array_map('trim', $items);
    return array_values(array_filter($items, 'strlen'));
  }

}
