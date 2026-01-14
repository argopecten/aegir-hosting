<?php

namespace Drupal\hosting_server\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

abstract class HostingServiceProviderBase extends PluginBase implements HostingServiceProviderInterface, ConfigurableInterface {

  use StringTranslationTrait;

  protected $configuration = [];

  public function getLabel(): string {
    return (string) ($this->pluginDefinition['label'] ?? $this->getPluginId());
  }

  public function getServiceType(): string {
    return (string) ($this->pluginDefinition['service_type'] ?? '');
  }

  public function defaultConfiguration(): array {
    return [];
  }

  public function getConfiguration(): array {
    return $this->configuration + $this->defaultConfiguration();
  }

  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

}
