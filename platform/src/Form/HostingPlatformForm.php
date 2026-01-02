<?php

namespace Drupal\hosting_platform\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Hosting platform add/edit forms.
 */
class HostingPlatformForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    if (function_exists('hosting_platform_apply_form_overrides')) {
      hosting_platform_apply_form_overrides($form, $form_state, $this->entity);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);
    $this->messenger()->addStatus($this->t('Saved %label.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.hosting_platform.collection');
    return $status;
  }

}
