<?php

namespace Drupal\hosting_server\Commands;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hosting_server\Entity\HostingServer;
use Drupal\hosting_server\Service\ServiceManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

class HostingServerCommands extends DrushCommands {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected ServiceManager $serviceManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, ServiceManager $service_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->serviceManager = $service_manager;
  }

  #[CLI\Command(name: 'hosting:servers:seed', aliases: ['hosting-servers-seed'])]
  #[CLI\Description('Create local web and database server records if missing.')]
  public function seed(): void {
    $host = gethostname() ?: 'localhost';
    $web_hostname = $host;
    $db_hostname = $host . '-db';
    if ($db_hostname === $web_hostname) {
      $db_hostname = 'localhost-db';
    }

    $web_server = $this->ensureServer($web_hostname, 'Local web server');
    $db_server = $this->ensureServer($db_hostname, 'Local database server');

    $this->ensureServiceInstance($web_server->id(), 'http', 'apache', []);

    $db_options = Database::getConnection()->getConnectionOptions();
    $db_config = [
      'db_user' => (string) ($db_options['username'] ?? ''),
      'db_passwd' => (string) ($db_options['password'] ?? ''),
      'port' => (int) ($db_options['port'] ?? 3306),
    ];
    if ($db_config['db_user'] === '') {
      $this->logger()->warning('Database username is empty; update the MySQL service instance config manually.');
    }
    $this->ensureServiceInstance($db_server->id(), 'db', 'mysql', $db_config);

    $this->logger()->success('Local web and database servers are ensured.');
  }

  protected function ensureServer(string $hostname, string $label): HostingServer {
    $storage = $this->entityTypeManager->getStorage('hosting_server');
    $existing = $storage->loadByProperties(['hostname' => $hostname]);
    if ($existing) {
      return reset($existing);
    }

    /** @var \Drupal\hosting_server\Entity\HostingServer $server */
    $server = $storage->create([
      'hostname' => $hostname,
      'human_name' => $label,
      'status' => HostingServer::STATUS_ENABLED,
      'verified' => \Drupal::time()->getRequestTime(),
    ]);
    $server->save();
    return $server;
  }

  protected function ensureServiceInstance(int $server_id, string $service_type, string $provider_id, array $config_overrides): void {
    $storage = $this->entityTypeManager->getStorage('hosting_service_instance');
    $existing = $storage->loadByProperties([
      'server' => $server_id,
      'service_type' => $service_type,
    ]);
    if ($existing) {
      return;
    }

    $provider = $this->serviceManager->createProvider($provider_id, []);
    $config = array_replace($provider->defaultConfiguration(), $config_overrides);

    $instance = $storage->create([
      'server' => $server_id,
      'service_type' => $service_type,
      'provider' => $provider_id,
      'available' => TRUE,
      'config' => Json::encode($config),
    ]);

    if (array_key_exists('port', $config)) {
      $instance->set('port', (int) $config['port']);
    }
    if (array_key_exists('restart_cmd', $config)) {
      $instance->set('restart_cmd', (string) $config['restart_cmd']);
    }

    $instance->save();
  }

}
