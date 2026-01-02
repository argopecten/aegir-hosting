<?php

namespace Drupal\hosting_site\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Hosting site add/edit forms.
 */
class HostingSiteForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['#entity'] = $this->entity;
    $form['#node'] = $this->entity;
    $form_state->set('node', $this->entity);

    hosting_site_apply_form_overrides($form, $form_state, $this->entity);

    if (!$this->entity->isNew()) {
      hosting_site_form_disable_widget($form, 'label', array('value'));
      hosting_site_form_disable_widget($form, 'profile', array('value'));
      hosting_site_form_disable_widget($form, 'platform', array('target_id'));
      hosting_site_form_disable_widget($form, 'language', array('value'));
      hosting_site_form_disable_widget($form, 'db_server', array('target_id'));
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $node = hosting_site_build_validation_stub($form, $form_state, $this->entity);
    hosting_site_validate($node, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);
    $this->messenger()->addStatus($this->t('Saved %label.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.hosting_site.collection');
    return $status;
  }

}
