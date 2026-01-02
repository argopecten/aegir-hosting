<?php

namespace Drupal\hosting_client\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class HostingClientSettingsForm extends ConfigFormBase {

  public function getFormId() {
    return 'hosting_client_configure';
  }

  protected function getEditableConfigNames() {
    return ['hosting_client.settings'];
  }

  protected function getSetting($key, $default = NULL) {
    $config_value = $this->config('hosting_client.settings')->get($key);
    return \Drupal::state()->get($key, $config_value !== NULL ? $config_value : $default);
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['hosting_client_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client internal name prefix'),
      '#description' => $this->t('Client nodes have an internal name that can be mapped to a UNIX group. This is the prefix assigned to that internal name to make sure it is in a separate namespace. Note that UNIX groups are generally limited to 16 characters so this prefix should be kept short. It can also be empty, in which case no prefix will be added.'),
      '#default_value' => $this->getSetting('hosting_client_prefix', ''),
      '#size' => 5,
      '#maxlength' => 16,
    ];
    $form['hosting_client_register_user'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically create user accounts for new clients.'),
      '#description' => $this->t('If this setting is on, any new client nodes will automatically have a system user account generated for them, and associated with the new client node. Users going through the signup form module have a user created regardless of this setting.'),
      '#default_value' => $this->getSetting('hosting_client_register_user', FALSE),
    ];
    $form['hosting_client_require_client_to_create_site'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require users to have a client to create a site.'),
      '#description' => $this->t('If this setting is on, users will not be able to create sites unless they are associated with a client.'),
      '#default_value' => $this->getSetting('hosting_client_require_client_to_create_site', FALSE),
    ];

    // User e-mail settings.
    $form['email'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('User e-mail settings'),
    ];
    $form['email']['hosting_client_send_welcome'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send welcome mail to new clients.'),
      '#description' => $this->t('If this setting is on, new clients will receive a welcome email containing their login details.'),
      '#default_value' => $this->getSetting('hosting_client_send_welcome', FALSE),
    ];
    $form['email']['hosting_client_mail_welcome_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject of welcome e-mail'),
      '#default_value' => $this->getSetting('hosting_client_mail_welcome_subject', _hosting_client_mail_text('welcome_subject')),
      '#maxlength' => 180,
      '#description' => $this->t('Customize the subject of your welcome e-mail, which is sent to new members upon registering.')
        . ' ' . $this->t('Available variables are:') . ' !username, !site, !password, !uri, !uri_brief, !date, !login_uri, !edit_uri, !login_url.',
    ];
    $form['email']['hosting_client_mail_welcome_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body of welcome e-mail'),
      '#default_value' => $this->getSetting('hosting_client_mail_welcome_body', _hosting_client_mail_text('welcome_body')),
      '#rows' => 15,
      '#description' => $this->t('Customize the body of the welcome e-mail, which is sent to new members upon registering.')
        . ' ' . $this->t('Available variables are:') . ' !username, !site, !password, !uri, !uri_brief, !login_uri, !edit_uri, !login_url.',
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('hosting_client.settings');
    $keys = [
      'hosting_client_prefix',
      'hosting_client_register_user',
      'hosting_client_require_client_to_create_site',
      'hosting_client_send_welcome',
      'hosting_client_mail_welcome_subject',
      'hosting_client_mail_welcome_body',
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
