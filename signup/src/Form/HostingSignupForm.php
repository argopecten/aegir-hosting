<?php

namespace Drupal\hosting_signup\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class HostingSignupForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hosting_signup_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $user = $this->currentUser();
    if (!(\Drupal::config('hosting_client.settings')->get('hosting_client_register_user') ?? FALSE) && !$user->id()) {
      $this->messenger()->addMessage($this->t('Please login first. We have no way of guessing your email and this site is configured not to register new users. You will not receive the login link for your new site. This is probably a bad configuration.'));
    }

    $site = $form_state->get('hosting_signup_site_entity');
    if (!$site instanceof \Drupal\Core\Entity\ContentEntityInterface || $site->getEntityTypeId() !== 'hosting_site') {
      $site = \Drupal::entityTypeManager()->getStorage('hosting_site')->create(['status' => HOSTING_SITE_QUEUED]);
    }
    $form_state->set('hosting_signup_site_entity', $site);

    $form['site'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $form['site'] = $form['site'] + hosting_site_form($site, $form_state);
    unset($form['site']['client']);
    \Drupal::moduleHandler()->alter('form', $form['site'], $form_state, 'hosting_site_add_form');
    unset($form['site']['info']['client']);
    hosting_signup_prefix_form_parents($form['site'], ['site']);
    hosting_site_apply_form_overrides($form['site'], $form_state, $site);

    $client = $form_state->get('hosting_signup_client_entity');
    if (!$client instanceof \Drupal\Core\Entity\ContentEntityInterface || $client->getEntityTypeId() !== 'hosting_client') {
      $client = \Drupal::entityTypeManager()->getStorage('hosting_client')->create(['status' => 1]);
    }
    $form_state->set('hosting_signup_client_entity', $client);

    $form['client'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $form['client'] = $form['client'] + hosting_client_form($client, $form_state);

    \Drupal::moduleHandler()->alter('form', $form['client'], $form_state, 'hosting_client_add_form');
    hosting_signup_prefix_form_parents($form['client'], ['client']);
    if ((\Drupal::config('hosting_client.settings')->get('hosting_client_register_user') ?? FALSE) && !$user->id()) {
      if (isset($form['client']['email']['widget'][0]['value'])) {
        $form['client']['email']['widget'][0]['value']['#required'] = TRUE;
      }
      $form['client']['email_confirm']['#required'] = TRUE;
    }

    $form['new_client'] = [
      '#type' => 'value',
      '#value' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sign up'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $site = $form_state->get('hosting_signup_site_entity');
    if (!$site instanceof \Drupal\Core\Entity\ContentEntityInterface || $site->getEntityTypeId() !== 'hosting_site') {
      $site = \Drupal::entityTypeManager()->getStorage('hosting_site')->create(['status' => HOSTING_SITE_QUEUED]);
    }

    $client = $form_state->get('hosting_signup_client_entity');
    if (!$client instanceof \Drupal\Core\Entity\ContentEntityInterface || $client->getEntityTypeId() !== 'hosting_client') {
      $client = \Drupal::entityTypeManager()->getStorage('hosting_client')->create(['status' => 1]);
    }

    $client_display = \Drupal::service('entity_display.repository')->getFormDisplay('hosting_client', 'default');
    $client_display->extractFormValues($client, $form['client'], $form_state);
    hosting_client_validate($client, $form['client'], $form_state);

    $site_display = \Drupal::service('entity_display.repository')->getFormDisplay('hosting_site', 'default');
    $site_display->extractFormValues($site, $form['site'], $form_state);
    $site_values = hosting_site_build_validation_stub($form['site'], $form_state, $site);
    $site_values->new_client = TRUE;
    $site_values->client = NULL;
    hosting_site_validate($site_values, $form['site'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $site = $form_state->get('hosting_signup_site_entity');
    if (!$site instanceof \Drupal\Core\Entity\ContentEntityInterface || $site->getEntityTypeId() !== 'hosting_site') {
      $site = \Drupal::entityTypeManager()->getStorage('hosting_site')->create(['status' => HOSTING_SITE_QUEUED]);
    }

    $client = $form_state->get('hosting_signup_client_entity');
    if (!$client instanceof \Drupal\Core\Entity\ContentEntityInterface || $client->getEntityTypeId() !== 'hosting_client') {
      $client = \Drupal::entityTypeManager()->getStorage('hosting_client')->create(['status' => 1]);
    }

    $client_display = \Drupal::service('entity_display.repository')->getFormDisplay('hosting_client', 'default');
    $client_display->extractFormValues($client, $form['client'], $form_state);
    if ($client->hasField('uid') && $client->get('uid')->isEmpty()) {
      $client->set('uid', $this->currentUser()->id());
    }
    $client->save();

    $site_display = \Drupal::service('entity_display.repository')->getFormDisplay('hosting_site', 'default');
    $site_display->extractFormValues($site, $form['site'], $form_state);
    $site->set('status', HOSTING_SITE_QUEUED);
    $site->set('client', $client->id());
    $site->save();

    $form_state->setRedirect('hosting_signup.thanks', [], [
      'query' => [
        'url' => $site->label(),
        'email' => $client->get('email')->value,
        'name' => $client->label(),
      ],
    ]);
  }

}
