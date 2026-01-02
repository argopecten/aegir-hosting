<?php

namespace Drupal\hosting_client\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hosting_platform\Entity\HostingPlatform;

class HostingClientPlatformAccessForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hosting_client_platform_access_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, HostingPlatform $hosting_platform = NULL) {
    if (!$hosting_platform) {
      return ['#markup' => $this->t('Platform not found.')];
    }

    $form_state->set('hosting_platform', $hosting_platform);

    $clients = hosting_platform_client_ids($hosting_platform);
    if (count($clients)) {
      foreach ($clients as $client) {
        $client_entity = hosting_get_client($client);
        $form['names'][$client] = [
          '#type' => 'markup',
          '#value' => $client_entity instanceof \Drupal\Core\Entity\EntityInterface
            ? \Drupal\Core\Link::fromTextAndUrl($client_entity->label(), $client_entity->toUrl('canonical'))->toString()
            : (string) $client,
        ];
        // Remove the label for the checkbox.
        $clients[$client] = '';
      }
    }
    else {
      $form['names']['_all'] = [
        '#type' => 'markup',
        '#value' => $this->t('All clients have access to this platform.'),
      ];
    }

    $form['clients'] = [
      '#type' => 'checkboxes',
      '#options' => $clients,
    ];
    $form['header'] = [
      '#type' => 'value',
      '#value' => [
        ['data' => $this->t('Allowed clients')],
        ['data' => $this->t('Remove')],
      ],
    ];
    $form['new_client'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Grant a client access to this platform'),
      '#weight' => 2,
      '#autocomplete_path' => 'hosting_client/autocomplete/client',
      '#description' => $this->t('This field allows you to grant a client access to this platform. Remove all clients from this list to grant all clients access to this platform.'),
    ];
    $form['#theme'] = 'hosting_client_platform_access_form';
    $form['apply'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#access' => $this->currentUser()->hasPermission('administer clients'),
      '#weight' => 10,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $new_client = $form_state->getValue('new_client');
    if (!$new_client) {
      return;
    }
    $client = hosting_get_client($new_client);
    if ($client) {
      return;
    }
    $add_client = '';
    if ($this->currentUser()->hasPermission('create client') || $this->currentUser()->hasPermission('administer clients')) {
      $add_client = ' ' . $this->t('or') . ' ' . \Drupal\Core\Link::fromTextAndUrl($this->t('add a new client'), \Drupal\Core\Url::fromRoute('entity.hosting_client.add_form'))->toString();
    }
    $form_state->setErrorByName('new_client', $this->t('The client name (%client) was not recognized. Please try again', ['%client' => $new_client]) . $add_client . '.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $platform = $form_state->get('hosting_platform');
    if (!$platform instanceof HostingPlatform) {
      return;
    }

    $clients = (array) $form_state->getValue('clients');
    $new_client = $form_state->getValue('new_client');

    foreach ($clients as $cid => $remove) {
      if ($remove) {
        \Drupal::database()->delete('hosting_platform_client_access')
          ->condition('pid', hosting_entity_id($platform))
          ->condition('cid', $cid)
          ->execute();
      }
    }

    if ($new_client) {
      $client = hosting_get_client($new_client);
      if ($client) {
        \Drupal::database()->insert('hosting_platform_client_access')
          ->fields([
            'pid' => hosting_entity_id($platform),
            'cid' => hosting_entity_id($client),
          ])
          ->execute();
      }
    }
  }

}
