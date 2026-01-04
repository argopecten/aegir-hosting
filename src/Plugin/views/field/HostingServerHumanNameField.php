<?php

namespace Drupal\hosting\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Human-readable name field for hosting servers.
 *
 * @ViewsField("hosting_server_human_name")
 */
class HostingServerHumanNameField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function allowAdvancedRender() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    if (!$value && isset($this->aliases['label']) && isset($values->{$this->aliases['label']})) {
      $value = $values->{$this->aliases['label']};
    }
    $value = $value ?: ($values->label ?? '');

    $server_id = $this->getValue($values, 'id');
    if (!$server_id && isset($values->id)) {
      $server_id = $values->id;
    }
    if (!$server_id) {
      $entity = $this->getEntity($values);
      $server_id = $entity ? $entity->id() : NULL;
    }
    if ($server_id) {
      $url = Url::fromRoute('entity.hosting_server.canonical', ['hosting_server' => $server_id]);
      return Link::fromTextAndUrl($value, $url)->toRenderable();
    }

    return $value;
  }

}
