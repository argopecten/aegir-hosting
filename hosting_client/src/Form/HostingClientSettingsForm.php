<?php

namespace Drupal\hosting_client\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HostingClientSettingsForm extends ConfigFormBase {

  protected EntityTypeManagerInterface $entityTypeManager;

  public static function create(ContainerInterface $container): static {
    /** @var static $instance */
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  public function getFormId(): string {
    return 'hosting_client_settings_form';
  }

  protected function getEditableConfigNames(): array {
    return ['hosting_client.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('hosting_client.settings');

    $form['client_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client prefix'),
      '#default_value' => (string) $config->get('client_prefix'),
      '#description' => $this->t('Prefix applied when generating client usernames.'),
    ];
    $form['auto_registration'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable auto-registration'),
      '#default_value' => (bool) $config->get('auto_registration'),
    ];
    $form['default_client'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Default client'),
      '#target_type' => 'hosting_client',
      '#default_value' => $config->get('default_client') ? $this->entityTypeManager->getStorage('hosting_client')->load($config->get('default_client')) : NULL,
      '#description' => $this->t('Fallback client when no explicit ownership is assigned.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory->getEditable('hosting_client.settings')
      ->set('client_prefix', (string) $form_state->getValue('client_prefix'))
      ->set('auto_registration', (bool) $form_state->getValue('auto_registration'))
      ->set('default_client', $form_state->getValue('default_client'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
