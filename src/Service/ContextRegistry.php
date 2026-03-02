<?php

namespace Drupal\hosting\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;

class ContextRegistry {

  /**
   * Flag to suppress insert hooks during module installation seeding.
   *
   * Set to TRUE by _hosting_server_seed_default_servers() to prevent the
   * server insert hook from saving contexts before service instances exist.
   */
  public static bool $seedingInProgress = FALSE;

  protected EntityTypeManagerInterface $entityTypeManager;
  protected PathValidatorInterface $pathValidator;
  protected LoggerInterface $logger;
  protected BackendInvokerInterface $backendInvoker;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, PathValidatorInterface $pathValidator, LoggerInterface $logger, BackendInvokerInterface $backendInvoker) {
    $this->entityTypeManager = $entityTypeManager;
    $this->pathValidator = $pathValidator;
    $this->logger = $logger;
    $this->backendInvoker = $backendInvoker;
  }

  public function register(string $context_name, string $entity_type, int $entity_id): void {
    $context_name = $this->normalizeContextName($context_name);
    $storage = $this->entityTypeManager->getStorage('hosting_context');
    $existing = $storage->loadByProperties(['context_name' => $context_name]);
    $context = $existing ? reset($existing) : NULL;

    if ($context) {
      $context->set('entity_type', $entity_type);
      $context->set('entity_id', $entity_id);
    }
    else {
      $context = $storage->create([
        'context_name' => $context_name,
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
      ]);
    }
    $context->save();

    if ($entity_id > 0 && $entity_type !== 'unknown') {
      $this->ensureAlias($context_name, $entity_type, $entity_id);
    }
  }

  public function unregisterByEntity(string $entity_type, int $entity_id): void {
    $storage = $this->entityTypeManager->getStorage('hosting_context');
    $contexts = $storage->loadByProperties([
      'entity_type' => $entity_type,
      'entity_id' => $entity_id,
    ]);
    if (!$contexts) {
      return;
    }

    foreach ($contexts as $context) {
      $this->deleteAlias($context->get('context_name')->value);
      $context->delete();
    }
  }

  public function loadByContext(string $context_name) {
    $context_name = $this->normalizeContextName($context_name);
    $storage = $this->entityTypeManager->getStorage('hosting_context');
    $contexts = $storage->loadByProperties(['context_name' => $context_name]);
    return $contexts ? reset($contexts) : NULL;
  }

  protected function normalizeContextName(string $context_name): string {
    return ltrim($context_name, '@');
  }

  protected function ensureAlias(string $context_name, string $entity_type, int $entity_id): void {
    try {
      $route = "entity.$entity_type.canonical";
      $path = Url::fromRoute($route, [$entity_type => $entity_id])->toString();
    }
    catch (\Throwable $e) {
      $this->logger->warning('Unable to build canonical path for context @context: @message', [
        '@context' => $context_name,
        '@message' => $e->getMessage(),
      ]);
      return;
    }

    $alias = '/hosting/c/' . $context_name;
    if (!$this->pathValidator->isValid($path)) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('path_alias');
    $existing = $storage->loadByProperties(['alias' => $alias]);
    $alias_entity = $existing ? reset($existing) : NULL;
    if ($alias_entity) {
      $alias_entity->setPath($path);
    }
    else {
      $alias_entity = $storage->create([
        'path' => $path,
        'alias' => $alias,
      ]);
    }
    $alias_entity->save();
  }

  protected function deleteAlias(string $context_name): void {
    $alias = '/hosting/c/' . $this->normalizeContextName($context_name);
    $storage = $this->entityTypeManager->getStorage('path_alias');
    $aliases = $storage->loadByProperties(['alias' => $alias]);
    if ($aliases) {
      $storage->delete($aliases);
    }
  }

  /**
   * Resolve the context name for a hosting entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   A hosting_server, hosting_platform, or hosting_site entity.
   *
   * @return string
   *   The context name (without @ prefix).
   */
  public function resolveContextName(ContentEntityInterface $entity): string {
    $type = $entity->getEntityTypeId();

    if ($type === 'hosting_server') {
      // First server is always server_master.
      if ((int) $entity->id() === 1) {
        return 'server_master';
      }
      $hostname = (string) $entity->get('hostname')->value;
      return 'server_' . $this->sanitizeName($hostname);
    }

    if ($type === 'hosting_platform') {
      $name = (string) $entity->get('name')->value;
      return $this->sanitizeName($name);
    }

    if ($type === 'hosting_site') {
      return (string) $entity->get('domain')->value;
    }

    throw new \InvalidArgumentException('Unsupported entity type: ' . $type);
  }

  /**
   * Resolve the provision context type string for an entity.
   */
  public function resolveContextType(ContentEntityInterface $entity): string {
    return match ($entity->getEntityTypeId()) {
      'hosting_server' => 'server',
      'hosting_platform' => 'platform',
      'hosting_site' => 'site',
      default => throw new \InvalidArgumentException('Unsupported entity type: ' . $entity->getEntityTypeId()),
    };
  }

  /**
   * Convert entity fields to backend context data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   A hosting entity.
   *
   * @return array<string, mixed>
   *   Data array suitable for provision:save --data.
   */
  public function entityToContextData(ContentEntityInterface $entity): array {
    return match ($entity->getEntityTypeId()) {
      'hosting_server' => $this->serverToContextData($entity),
      'hosting_platform' => $this->platformToContextData($entity),
      'hosting_site' => $this->siteToContextData($entity),
      default => [],
    };
  }

  /**
   * Save an entity's context to the backend via provision:save.
   *
   * Writes the YAML alias file and registers the hosting_context record.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   A hosting entity.
   *
   * @return string
   *   The context name.
   *
   * @throws \RuntimeException
   *   If the backend save fails.
   */
  public function saveContextToBackend(ContentEntityInterface $entity): string {
    $contextName = $this->resolveContextName($entity);
    $contextType = $this->resolveContextType($entity);
    $data = $this->entityToContextData($entity);

    $result = $this->backendInvoker->invoke(
      'provision:save',
      [$contextName],
      [
        'type' => $contextType,
        'data' => json_encode($data),
      ]
    );

    if (!empty($result['exit_code'])) {
      $this->logger->error('Backend provision:save failed for @context: @error', [
        '@context' => $contextName,
        '@error' => $result['error'] ?? 'Unknown error',
      ]);
      throw new \RuntimeException('Failed to save context to backend: ' . $contextName);
    }

    // Register the hosting_context entity and path alias.
    $this->register($contextName, $entity->getEntityTypeId(), (int) $entity->id());

    $this->logger->info('Saved context @context for @type @id.', [
      '@context' => $contextName,
      '@type' => $entity->getEntityTypeId(),
      '@id' => $entity->id(),
    ]);

    return $contextName;
  }

  /**
   * Delete an entity's context from the backend.
   *
   * @param string $contextName
   *   The context name to delete.
   * @param string $entity_type
   *   The entity type ID.
   * @param int $entity_id
   *   The entity ID.
   */
  public function deleteContextFromBackend(string $contextName, string $entity_type, int $entity_id): void {
    $result = $this->backendInvoker->invoke(
      'provision:save',
      [$contextName],
      ['delete' => TRUE]
    );

    if (!empty($result['exit_code'])) {
      $this->logger->warning('Backend context delete failed for @context: @error', [
        '@context' => $contextName,
        '@error' => $result['error'] ?? 'Unknown error',
      ]);
    }

    $this->unregisterByEntity($entity_type, $entity_id);
  }

  /**
   * Look up a server entity's context name from its entity ID.
   *
   * @param int $server_id
   *   The hosting_server entity ID.
   *
   * @return string
   *   The context name, or empty string if not found.
   */
  public function getServerContextName(int $server_id): string {
    $storage = $this->entityTypeManager->getStorage('hosting_context');
    $records = $storage->loadByProperties([
      'entity_type' => 'hosting_server',
      'entity_id' => $server_id,
    ]);
    if ($records) {
      return (string) reset($records)->get('context_name')->value;
    }
    // Fallback: load the server and resolve directly.
    $server = $this->entityTypeManager->getStorage('hosting_server')->load($server_id);
    if ($server) {
      return $this->resolveContextName($server);
    }
    return '';
  }

  /**
   * Sanitize a string for use as a context name.
   */
  protected function sanitizeName(string $name): string {
    // Lowercase, replace non-alphanumeric with underscores, collapse multiples.
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9]+/', '_', $name);
    return trim($name, '_');
  }

  /**
   * Build context data for a server entity.
   */
  protected function serverToContextData(ContentEntityInterface $server): array {
    $data = [
      'aegir_root' => getenv('HOME') ?: '/var/aegir',
      'remote_host' => (string) $server->get('hostname')->value,
      'script_user' => get_current_user(),
    ];

    // Load service instances for this server.
    $instances = $this->entityTypeManager
      ->getStorage('hosting_service_instance')
      ->loadByProperties(['server' => $server->id()]);

    foreach ($instances as $instance) {
      $serviceType = (string) $instance->get('service_type')->value;
      $provider = (string) $instance->get('provider')->value;
      $port = (int) $instance->get('port')->value;

      if ($serviceType === 'http') {
        $data['http_service_type'] = $provider;
        if ($port > 0) {
          $data['http_port'] = $port;
        }
      }
      elseif ($serviceType === 'db') {
        $data['db_service_type'] = $provider;
        if ($port > 0) {
          $data['db_port'] = $port;
        }
        // Extract db credentials from service instance config.
        $config = json_decode((string) $instance->get('config')->value, TRUE);
        if (is_array($config)) {
          if (!empty($config['db_user'])) {
            $data['master_db_user'] = $config['db_user'];
          }
          if (!empty($config['db_passwd'])) {
            $data['master_db_passwd'] = $config['db_passwd'];
          }
        }
      }
    }

    return $data;
  }

  /**
   * Build context data for a platform entity.
   */
  protected function platformToContextData(ContentEntityInterface $platform): array {
    $root = (string) $platform->get('publish_path')->value;
    $data = [
      'root' => $root,
    ];

    // Resolve web server reference to its context name.
    $webServerRef = $platform->get('web_server');
    if (!$webServerRef->isEmpty()) {
      $serverId = (int) $webServerRef->target_id;
      $serverContext = $this->getServerContextName($serverId);
      if ($serverContext !== '') {
        $data['server'] = $serverContext;
      }
    }

    return $data;
  }

  /**
   * Build context data for a site entity.
   */
  protected function siteToContextData(ContentEntityInterface $site): array {
    $data = [
      'uri' => (string) $site->get('domain')->value,
    ];

    // Resolve platform reference to context name.
    $platformRef = $site->get('platform');
    if (!$platformRef->isEmpty()) {
      $platformId = (int) $platformRef->target_id;
      $contextStorage = $this->entityTypeManager->getStorage('hosting_context');
      $records = $contextStorage->loadByProperties([
        'entity_type' => 'hosting_platform',
        'entity_id' => $platformId,
      ]);
      if ($records) {
        $data['platform'] = (string) reset($records)->get('context_name')->value;
      }
      // Also resolve root from platform entity.
      $platform = $this->entityTypeManager->getStorage('hosting_platform')->load($platformId);
      if ($platform) {
        $data['root'] = (string) $platform->get('publish_path')->value;
      }
    }

    // Resolve db_server reference to context name.
    $dbServerRef = $site->get('db_server');
    if (!$dbServerRef->isEmpty()) {
      $serverId = (int) $dbServerRef->target_id;
      $serverContext = $this->getServerContextName($serverId);
      if ($serverContext !== '') {
        $data['db_server'] = $serverContext;
      }
    }

    // Include optional site fields.
    $dbName = (string) $site->get('db_name')->value;
    if ($dbName !== '') {
      $data['db_name'] = $dbName;
    }

    $profile = $site->get('profile');
    if (!$profile->isEmpty() && $profile->entity) {
      $data['profile'] = (string) $profile->entity->label();
    }

    $language = (string) $site->get('language')->value;
    if ($language !== '') {
      $data['language'] = $language;
    }

    // Sync cron_key to provision context for future managed-site cron.
    if ($site->hasField('cron_key')) {
      $cronKey = (string) $site->get('cron_key')->value;
      if ($cronKey !== '') {
        $data['cron_key'] = $cronKey;
      }
    }

    return $data;
  }

}
