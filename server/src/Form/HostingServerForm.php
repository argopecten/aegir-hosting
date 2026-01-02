<?php

namespace Drupal\hosting_server\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Hosting server add/edit forms.
 */
class HostingServerForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    if (function_exists('hosting_server_apply_form_overrides')) {
      hosting_server_apply_form_overrides($form, $form_state, $this->entity);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);
    $this->messenger()->addStatus($this->t('Saved %label.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.hosting_server.collection');
    return $status;
  }

}
