<?php

namespace Drupal\hosting\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;

class ContextRegistry {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected PathValidatorInterface $pathValidator;
  protected LoggerInterface $logger;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, PathValidatorInterface $pathValidator, LoggerInterface $logger) {
    $this->entityTypeManager = $entityTypeManager;
    $this->pathValidator = $pathValidator;
    $this->logger = $logger;
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

}
