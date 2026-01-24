<?php

namespace Drupal\hosting\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hosting\Service\FeatureManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HostingFeaturesForm extends ConfigFormBase {

  protected FeatureManager $featureManager;

  public function __construct(FeatureManager $featureManager) {
    $this->featureManager = $featureManager;
  }

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('hosting.feature_manager')
    );
  }

  public function getFormId(): string {
    return 'hosting_features_form';
  }

  protected function getEditableConfigNames(): array {
    return ['hosting.features'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $features = $this->featureManager->getFeatures();
    $enabled = $this->featureManager->getEnabled();

    $form['features'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Features'),
      '#description' => $this->t('Enable or disable Hosting features.'),
    ];

    foreach ($features as $key => $info) {
      $locked = !empty($info['required']);
      $default_value = $locked ? TRUE : in_array($key, $enabled, TRUE);
      $form['features'][$key] = [
        '#type' => 'checkbox',
        '#title' => $info['title'],
        '#description' => $info['description'],
        '#default_value' => $default_value,
        '#disabled' => $locked,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValue('features') ?? [];
    $enabled = [];
    foreach ($values as $key => $value) {
      if ($value) {
        $enabled[] = $key;
      }
    }

    $this->featureManager->applyEnabledFeatures($enabled);

    parent::submitForm($form, $form_state);
  }

}
