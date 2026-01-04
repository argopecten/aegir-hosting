<?php

namespace Drupal\hosting\Plugin\views\field;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Services field for hosting servers.
 *
 * @ViewsField("hosting_server_services")
 */
class HostingServerServicesField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function label() {
    return parent::label();
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $server_id = $this->getValue($values);
    if (!$server_id) {
      return '';
    }

    $all_services = hosting_server_services();
    $types = [];
    foreach ($all_services as $type => $service) {
      $types[$type] = 'no';
    }

    $records = \Drupal::database()->query(
      'SELECT service, type FROM {hosting_service} WHERE server_id = :server_id',
      [':server_id' => $server_id]
    );
    foreach ($records as $record) {
      $types[$record->service] = $record->type ?: 'yes';
    }

    $label = trim((string) $this->label());
    if ($label !== '' && str_contains($label, ',')) {
      $labels = array_map('trim', explode(',', $label));
      $http_type = $types['http'] ?? '';
      $has_ssl = $http_type && str_contains($http_type, 'ssl');
      if (!$has_ssl) {
        $has_ssl = (bool) \Drupal::database()->select('hosting_ssl_server', 'ssl')
          ->fields('ssl', ['server_id'])
          ->condition('server_id', $server_id)
          ->range(0, 1)
          ->execute()
          ->fetchField();
      }

      $ordered = [];
      foreach ($labels as $label_text) {
        $key = strtolower($label_text);
        if (str_contains($key, 'database')) {
          $ordered[] = $types['db'] ?? 'no';
          continue;
        }
        if (str_contains($key, 'web')) {
          $ordered[] = $types['http'] ?? 'no';
          continue;
        }
        if (str_contains($key, 'certificate') || str_contains($key, 'ssl')) {
          $ordered[] = $has_ssl ? ($http_type ?: 'yes') : 'no';
          continue;
        }
        if (str_contains($key, 'remote')) {
          $ordered[] = $types['remote'] ?? 'no';
          continue;
        }
        $ordered[] = 'no';
      }

      return implode(',', $ordered);
    }

    return implode(',', $types);
  }

}
