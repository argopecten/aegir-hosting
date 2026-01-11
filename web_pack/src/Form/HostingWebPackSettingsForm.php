<?php

namespace Drupal\hosting_web_pack\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Hosting Web Pack settings.
 */
class HostingWebPackSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hosting_web_pack_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['hosting_web_pack.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('hosting_web_pack.settings');

    $form['hosting_web_pack_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable web packs'),
      '#description' => $this->t('Allow grouping of web server configurations into packs.'),
      '#default_value' => $config->get('enabled') ?? FALSE,
    ];

    $form['hosting_web_pack_default_pack'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default web pack'),
      '#description' => $this->t('The default web pack to use for new sites.'),
      '#default_value' => $config->get('default_pack') ?? 'standard',
      '#maxlength' => 64,
      '#states' => [
        'visible' => [
          ':input[name="hosting_web_pack_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_web_pack_allow_custom'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow custom web packs'),
      '#description' => $this->t('Allow users to create custom web pack configurations.'),
      '#default_value' => $config->get('allow_custom') ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="hosting_web_pack_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_web_pack_inheritance'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable pack inheritance'),
      '#description' => $this->t('Allow web packs to inherit settings from parent packs.'),
      '#default_value' => $config->get('inheritance') ?? TRUE,
      '#states' => [
        'visible' => [
          ':input[name="hosting_web_pack_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_web_pack_cache_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cache settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="hosting_web_pack_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_web_pack_cache_settings']['hosting_web_pack_enable_caching'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable caching'),
      '#description' => $this->t('Enable default caching in web packs.'),
      '#default_value' => $config->get('enable_caching') ?? TRUE,
    ];

    $form['hosting_web_pack_cache_settings']['hosting_web_pack_cache_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache TTL'),
      '#description' => $this->t('Default time-to-live for cached content in seconds.'),
      '#default_value' => $config->get('cache_ttl') ?? 3600,
      '#min' => 0,
    ];

    $form['hosting_web_pack_security'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Security settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="hosting_web_pack_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hosting_web_pack_security']['hosting_web_pack_enable_security_headers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable security headers'),
      '#description' => $this->t('Add security headers to responses by default.'),
      '#default_value' => $config->get('enable_security_headers') ?? TRUE,
    ];

    $form['hosting_web_pack_security']['hosting_web_pack_enable_rate_limiting'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable rate limiting'),
      '#description' => $this->t('Apply rate limiting to requests by default.'),
      '#default_value' => $config->get('enable_rate_limiting') ?? FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('hosting_web_pack.settings')
      ->set('enabled', $form_state->getValue('hosting_web_pack_enabled'))
      ->set('default_pack', $form_state->getValue('hosting_web_pack_default_pack'))
      ->set('allow_custom', $form_state->getValue('hosting_web_pack_allow_custom'))
      ->set('inheritance', $form_state->getValue('hosting_web_pack_inheritance'))
      ->set('enable_caching', $form_state->getValue('hosting_web_pack_enable_caching'))
      ->set('cache_ttl', $form_state->getValue('hosting_web_pack_cache_ttl'))
      ->set('enable_security_headers', $form_state->getValue('hosting_web_pack_enable_security_headers'))
      ->set('enable_rate_limiting', $form_state->getValue('hosting_web_pack_enable_rate_limiting'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
