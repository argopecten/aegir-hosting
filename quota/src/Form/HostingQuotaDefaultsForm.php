<?php

namespace Drupal\hosting_quota\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class HostingQuotaDefaultsForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hosting_quota_admin_defaults_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $all_resources = \Drupal::moduleHandler()->invokeAll('hosting_quota_resource');
    $config = \Drupal::config('hosting_quota.settings');

    $form['info'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Set default limits for each resource. This value will be used as the default value when new clients are created and to set quota limits for all clients currently without limits.'),
    ];

    $form['quota'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Client quota settings'),
      '#access' => $this->currentUser()->hasPermission('edit all quotas'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];

    foreach ($all_resources as $resource => $quota) {
      $form['quota'][$resource] = [
        '#title' => \Drupal\Component\Utility\Html::escape($quota['title']),
        '#description' => \Drupal\Component\Utility\Html::escape($quota['description']),
        '#type' => 'textfield',
        '#default_value' => $config->get('default_limits.' . $resource) ?? 0,
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['#tree'] = TRUE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::configFactory()->getEditable('hosting_quota.settings');
    foreach ((array) $form_state->getValue('quota') as $resource => $quota) {
      $config->set('default_limits.' . $resource, (int) $quota);

      $client_result = \Drupal::database()->query('SELECT id FROM {hosting_client} WHERE id not in (SELECT client FROM {hosting_client_quota})');
      while ($row = $client_result->fetch()) {
        hosting_quota_set_limit($row->id, $resource, $quota);
      }
    }
    $config->save();
    $this->messenger()->addStatus($this->t('Default quotas updated.'));
  }

}
