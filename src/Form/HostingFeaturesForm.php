<?php

namespace Drupal\hosting\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class HostingFeaturesForm extends ConfigFormBase {

  public function getFormId() {
    return 'hosting_features_form';
  }

  protected function getEditableConfigNames() {
    return ['hosting.features'];
  }

  protected function getFormSettings() {
    return $this->config('hosting.features')->get('hosting_features_form_settings') ?? [];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'hosting/admin';

    $settings = $this->getFormSettings();

    $form['settings'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Settings'),
      '#options' => [
        'dependencies' => $this->t('Display dependencies'),
        'roles' => $this->t('Display roles and permissions'),
      ],
      '#default_value' => $settings ?: [],
      '#weight' => -20,
    ];

    $optional = [
      '#type' => 'fieldset',
      '#title' => $this->t('Optional system features'),
      '#description' => $this->t('You may choose any of the additional system features from the list below.'),
      '#collapsible' => FALSE,
    ];

    $required = [
      '#type' => 'fieldset',
      '#title' => $this->t('Required system features'),
      '#collapsed' => TRUE,
      '#collapsible' => TRUE,
      '#weight' => -10,
      '#description' => $this->t("These features are central to Aegir's functionality, and thus cannot be disabled."),
    ];

    $experimental = [
      '#type' => 'fieldset',
      '#title' => $this->t('Experimental'),
      '#collapsed' => TRUE,
      '#collapsible' => TRUE,
      '#description' => $this->t('Features marked experimental have not been completed to a satisfactory level to be considered production ready, so use at your own risk.'),
    ];

    $advanced = [
      '#type' => 'fieldset',
      '#title' => $this->t('Advanced'),
      '#collapsed' => TRUE,
      '#collapsible' => TRUE,
      '#description' => $this->t('Advanced features require a deep knowledge of Aegir and Drupal to be considered safe, so use at your own risk.'),
    ];

    $features = hosting_get_features(TRUE);
    foreach ($features as $feature => $info) {
      $description = $info['description'];

      if ($settings && !empty($settings['dependencies'])) {
        // Gather dependencies and their statuses.
        $depends_on = isset($info['dependencies']['features']) ? $info['dependencies']['features'] : FALSE;
        if (is_array($depends_on)) {
          $description .= \Drupal::service('theme.manager')->render('hosting_feature_dependencies', [
            'dependencies' => $depends_on,
            'prefix' => 'Depends on',
            'features' => $features,
          ]);
        }

        // Gather relying features and their statuses.
        $required_by = isset($info['dependencies']['reverse']) ? $info['dependencies']['reverse'] : FALSE;
        if (is_array($required_by)) {
          $description .= \Drupal::service('theme.manager')->render('hosting_feature_dependencies', [
            'dependencies' => $required_by,
            'prefix' => 'Required by',
            'features' => $features,
          ]);
        }
      }

      if ($settings && !empty($settings['roles'])) {
        // Add collapsed fieldset listing the feature's permissions assigned per role.
        $role_perms = isset($info['role_permissions']) ? $info['role_permissions'] : FALSE;
        if (is_array($role_perms)) {
          $element = \Drupal::formBuilder()->getForm('hosting_feature_role_perms_table', $role_perms);
          $description .= \Drupal::service('renderer')->render($element);
        }
      }

      // Disable checkbox for required features.
      $locked = FALSE;
      if ($info['status'] == HOSTING_FEATURE_REQUIRED) {
        $locked = TRUE;
      }
      elseif (isset($required_by) && $required_by) {
        foreach ($required_by as $mod => $feat) {
          $locked = $features[$feat]['enabled'] ? TRUE : $locked;
        }
      }

      $element = [
        '#type' => 'checkbox',
        '#title' => Html::escape($info['title']),
        '#description' => $description,
        '#default_value' => $info['status'] == HOSTING_FEATURE_REQUIRED ? 1 : hosting_feature($feature),
        '#required' => hosting_feature($feature) == HOSTING_FEATURE_REQUIRED,
        '#disabled' => $locked,
      ];

      // Add another fieldset based on contrib module package.
      $package = FALSE;
      if ($info['package'] != 'Hosting') {
        $package = [
          '#type' => 'fieldset',
          '#title' => $info['package'],
          '#collapsed' => FALSE,
          '#collapsible' => TRUE,
        ];
      }
      if ($package) {
        if ($info['group'] == 'required') {
          if (!isset($required[$info['package']])) {
            $required[$info['package']] = $package;
          }
          $required[$info['package']]['hosting_feature_' . $feature] = $element;
        }
        elseif ($info['group'] == 'optional') {
          if (!isset($optional[$info['package']])) {
            $optional[$info['package']] = $package;
          }
          $optional[$info['package']]['hosting_feature_' . $feature] = $element;
        }
        elseif ($info['group'] == 'advanced') {
          if (!isset($advanced[$info['package']])) {
            $advanced[$info['package']] = $package;
          }
          $advanced[$info['package']]['hosting_feature_' . $feature] = $element;
        }
        else {
          if (!isset($experimental[$info['package']])) {
            $experimental[$info['package']] = $package;
          }
          $experimental[$info['package']]['hosting_feature_' . $feature] = $element;
        }
      }
      // This feature is in the 'Hosting' module package.
      else {
        if (isset($info['group']) && $info['group'] == 'required') {
          $required['hosting_feature_' . $feature] = $element;
        }
        elseif (isset($info['group']) && $info['group'] == 'optional') {
          $optional['hosting_feature_' . $feature] = $element;
        }
        elseif (isset($info['group']) && $info['group'] == 'advanced') {
          $advanced['hosting_feature_' . $feature] = $element;
        }
        else {
          $experimental['hosting_feature_' . $feature] = $element;
        }
      }
    }

    $form['required'] = $required;
    $form['optional'] = $optional;
    $form['advanced'] = $advanced;
    $form['experimental'] = $experimental;

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = $form_state->getValue('settings') ?: [];
    $this->configFactory->getEditable('hosting.features')
      ->set('hosting_features_form_settings', $settings)
      ->save();

    $values = array_filter($form_state->getValues(), 'is_numeric');
    $features = hosting_determine_features_status($values);

    $rebuild_on_enable = count($features['disable']) ? FALSE : TRUE;
    hosting_features_enable($features['enable'], $rebuild_on_enable);
    hosting_features_disable($features['disable']);

    parent::submitForm($form, $form_state);
  }

}
