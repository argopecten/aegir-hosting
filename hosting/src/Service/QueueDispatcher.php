<?php

namespace Drupal\hosting\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Psr\Log\LoggerInterface;

class QueueDispatcher {

  protected ConfigFactoryInterface $configFactory;
  protected KeyValueFactoryInterface $keyValueFactory;
  protected QueueRunner $queueRunner;
  protected TimeInterface $time;
  protected LoggerInterface $logger;
  protected Connection $database;
  protected ModuleHandlerInterface $moduleHandler;

  public function __construct(ConfigFactoryInterface $configFactory, KeyValueFactoryInterface $keyValueFactory, QueueRunner $queueRunner, TimeInterface $time, LoggerInterface $logger, Connection $database, ModuleHandlerInterface $moduleHandler) {
    $this->configFactory = $configFactory;
    $this->keyValueFactory = $keyValueFactory;
    $this->queueRunner = $queueRunner;
    $this->time = $time;
    $this->logger = $logger;
    $this->database = $database;
    $this->moduleHandler = $moduleHandler;
  }

  public function dispatch(): void {
    $config = $this->configFactory->get('hosting.settings');
    if (!$config->get('dispatch_enabled')) {
      return;
    }

    foreach ($this->getQueuesWithState() as $queue_id => $queue) {
      if (!$queue['enabled'] || empty($queue['queue_id'])) {
        continue;
      }
      if ($queue['next_run'] > $this->time->getRequestTime()) {
        continue;
      }
      if ($queue['calc_items'] <= 0) {
        continue;
      }
      $this->queueRunner->run($queue['queue_id'], (int) $queue['calc_items']);
      $this->setLastRun($queue_id, $this->time->getRequestTime());
    }
  }

  public function getQueuesWithState(): array {
    $config = $this->configFactory->get('hosting.settings');
    $queues_config = $config->get('queues') ?? [];
    $queues = $this->moduleHandler->invokeAll('hosting_queue_info');
    foreach ($queues_config as $queue_id => $queue_config) {
      $queues[$queue_id] = ($queues[$queue_id] ?? []) + $queue_config;
    }

    $state = $this->keyValueFactory->get('hosting.queue_state');
    $now = $this->time->getRequestTime();

    $result = [];
    foreach ($queues as $queue_id => $queue) {
      $last_run = (int) $state->get($queue_id . '.last_run', 0);
      $queue = $queue + [
        'type' => 'serial',
        'frequency' => 0,
        'items' => 0,
        'enabled' => TRUE,
        'max_threads' => 6,
        'min_threads' => 1,
        'threshold' => 100,
      ];

      $total_items = isset($queue['total_items']) ? (int) $queue['total_items'] : $this->getQueueSize($queue['queue_id'] ?? '');
      $calc = $this->calculateSchedule($queue['type'], (int) $queue['frequency'], (int) $queue['items'], $total_items, (int) $queue['threshold'], (int) $queue['min_threads'], (int) $queue['max_threads']);

      $next_run = $calc['calc_frequency'] ? $last_run + $calc['calc_frequency'] : 0;
      if ($last_run === 0 && $calc['calc_frequency'] > 0) {
        $next_run = $now;
      }

      $result[$queue_id] = [
        'label' => $queue['label'] ?? $queue_id,
        'description' => $queue['description'] ?? '',
        'enabled' => (bool) $queue['enabled'],
        'frequency' => (int) $queue['frequency'],
        'items' => (int) $queue['items'],
        'queue_id' => $queue['queue_id'] ?? NULL,
        'last_run' => $last_run,
        'next_run' => $next_run,
        'type' => $queue['type'],
        'total_items' => $total_items,
        'calc_frequency' => $calc['calc_frequency'],
        'calc_items' => $calc['calc_items'],
        'calc_threads' => $calc['calc_threads'],
      ];
    }

    return $result;
  }

  protected function calculateSchedule(string $type, int $frequency, int $items, int $total_items, int $threshold, int $min_threads, int $max_threads): array {
    if ($type === 'batch') {
      $threads = $threshold > 0 ? (int) ceil($total_items / $threshold) : 1;
      if ($threads <= $min_threads) {
        $threads = $min_threads;
      }
      elseif ($threads > $max_threads) {
        $threads = $max_threads;
      }
      $calc_frequency = $threads > 0 ? (int) ceil($frequency / $threads) : $frequency;
      $calc_items = $threads > 0 ? (int) ceil($total_items / $threads) : $items;
      return [
        'calc_frequency' => $calc_frequency,
        'calc_items' => $calc_items,
        'calc_threads' => $threads,
      ];
    }

    if ($type === 'spread') {
      if ($total_items > 0) {
        $calc_frequency = max(60, (int) floor($frequency / $total_items));
        $calc_items = (int) ceil(($calc_frequency / max($frequency, 1)) * $total_items);
      }
      else {
        $calc_frequency = 86400;
        $calc_items = 0;
      }
      return [
        'calc_frequency' => $calc_frequency,
        'calc_items' => $calc_items,
        'calc_threads' => 0,
      ];
    }

    return [
      'calc_frequency' => $frequency,
      'calc_items' => $items,
      'calc_threads' => 1,
    ];
  }

  protected function getQueueSize(string $queue_name): int {
    if ($queue_name === '') {
      return 0;
    }
    if (!$this->database->schema()->tableExists('queue')) {
      return 0;
    }

    try {
      return (int) $this->database->select('queue', 'q')
        ->condition('name', $queue_name)
        ->countQuery()
        ->execute()
        ->fetchField();
    }
    catch (\Throwable $e) {
      $this->logger->warning('Unable to read queue size for @queue: @message', [
        '@queue' => $queue_name,
        '@message' => $e->getMessage(),
      ]);
      return 0;
    }
  }

  protected function setLastRun(string $queue_id, int $timestamp): void {
    $state = $this->keyValueFactory->get('hosting.queue_state');
    $state->set($queue_id . '.last_run', $timestamp);
  }

}
