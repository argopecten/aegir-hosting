<?php

namespace Drupal\hosting_db_server\Service;

use Drupal\Component\Serialization\Json;
use Drupal\hosting_server\Entity\HostingServiceInstance;

class DbCredentialsManager {

  public function getCredentials(HostingServiceInstance $instance): array {
    $config = $this->decodeConfig((string) $instance->get('config')->value);

    return [
      'user' => (string) ($config['db_user'] ?? ''),
      'password' => (string) ($config['db_passwd'] ?? ''),
      'port' => (int) ($config['port'] ?? 3306),
    ];
  }

  public function setCredentials(HostingServiceInstance $instance, array $credentials): void {
    $config = $this->decodeConfig((string) $instance->get('config')->value);
    $config['db_user'] = (string) ($credentials['user'] ?? $config['db_user'] ?? '');
    $config['db_passwd'] = (string) ($credentials['password'] ?? $config['db_passwd'] ?? '');
    $config['port'] = (int) ($credentials['port'] ?? $config['port'] ?? 3306);

    $instance->set('config', Json::encode($config));
  }

  public function buildDsn(HostingServiceInstance $instance, string $host): string {
    $creds = $this->getCredentials($instance);
    $user = rawurlencode($creds['user']);
    $pass = rawurlencode($creds['password']);
    $port = $creds['port'];

    $host = $port ? $host . ':' . $port : $host;
    return sprintf('mysql://%s:%s@%s', $user, $pass, $host);
  }

  protected function decodeConfig(string $value): array {
    if ($value === '') {
      return [];
    }
    $decoded = Json::decode($value);
    return is_array($decoded) ? $decoded : [];
  }

}
