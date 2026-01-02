<?php

namespace Drupal\hosting_alias\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class HostingAliasSettingsForm extends ConfigFormBase {

  public function getFormId() {
    return 'hosting_alias_settings';
  }

  protected function getEditableConfigNames() {
    return ['hosting_alias.settings'];
  }

  protected function getSetting($key, $default = NULL) {
    $config_value = $this->config('hosting_alias.settings')->get($key);
    return \Drupal::state()->get($key, $config_value !== NULL ? $config_value : $default);
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['hosting_alias_subdomain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Domain used for automatic subdomain hosting'),
      '#description' => $this->t('To be able to provide a temporary url for your sites, you need to have configured a wild card dns entry<br /> resolving all calls to subdomains of your chosen domain, to point at your web server.'),
      '#default_value' => $this->getSetting('hosting_alias_subdomain', ''),
    ];
    $form['hosting_alias_subdomain_replace_dash'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Replace dashes'),
      '#description' => $this->t('Replace dashes in automatic subdomain aliases. Changes only take effect when a site is verified.'),
      '#default_value' => $this->getSetting('hosting_alias_subdomain_replace_dash', TRUE),
    ];
    $form['hosting_alias_subdomain_dash_substitute'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dash substitute'),
      '#description' => $this->t('Dashes in automatic subdomain aliases will be replaced with the given value. Changes only take effect when a site is verified.'),
      '#default_value' => $this->getSetting('hosting_alias_subdomain_dash_substitute', '--'),
      '#states' => [
        'visible' => [
          ':input[name="hosting_alias_subdomain_replace_dash"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['hosting_alias_automatic_www'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate www.domain.com alias automatically'),
      '#description' => $this->t('If a domain name does not start with www., automatically create an alias for www.domain?'),
      '#default_value' => $this->getSetting('hosting_alias_automatic_www', FALSE),
    ];
    $form['hosting_alias_automatic_no_www'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate domain.com alias automatically'),
      '#description' => $this->t('If a domain name starts with www., automatically create an alias for domain.com?'),
      '#default_value' => $this->getSetting('hosting_alias_automatic_no_www', FALSE),
    ];

    $form['hosting_alias_redirection'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use redirects instead of aliases by default'),
      '#description' => $this->t('Instead of serving the primary domain under a symlinked site alias, this module can also redirect the user to the primary domain from an alias. This setting can be controlled per site. Setting this option here will make redirection the default behavior for site aliases.'),
      '#default_value' => $this->getSetting('hosting_alias_redirection', FALSE),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $subdomain = trim((string) $form_state->getValue('hosting_alias_subdomain'));
    if ($subdomain != '') {
      $is_valid = UrlHelper::isValid($subdomain, TRUE) || UrlHelper::isValid('http://' . $subdomain, TRUE);
      if (!$is_valid) {
        $form_state->setErrorByName('hosting_alias_subdomain', $this->t('The provided domain is invalid.'));
      }
    }

    parent::validateForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('hosting_alias.settings');
    $keys = [
      'hosting_alias_subdomain',
      'hosting_alias_subdomain_replace_dash',
      'hosting_alias_subdomain_dash_substitute',
      'hosting_alias_automatic_www',
      'hosting_alias_automatic_no_www',
      'hosting_alias_redirection',
    ];

    foreach ($keys as $key) {
      $value = $form_state->getValue($key);
      $config->set($key, $value);
      \Drupal::state()->set($key, $value);
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
