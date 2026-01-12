<?php

namespace Drupal\hosting\Service;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class QueueRunner {

  protected QueueFactory $queueFactory;
  protected QueueWorkerManagerInterface $queueWorkerManager;
  protected LoggerInterface $logger;

  public function __construct(QueueFactory $queueFactory, QueueWorkerManagerInterface $queueWorkerManager, LoggerInterface $logger) {
    $this->queueFactory = $queueFactory;
    $this->queueWorkerManager = $queueWorkerManager;
    $this->logger = $logger;
  }

  public function run(string $queue_id, int $count): void {
    $queue = $this->queueFactory->get($queue_id);
    $worker = $this->queueWorkerManager->createInstance($queue_id);

    for ($i = 0; $i < $count; $i++) {
      $item = $queue->claimItem();
      if (!$item) {
        break;
      }

      try {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
      }
      catch (Throwable $e) {
        $queue->releaseItem($item);
        $this->logger->error('Queue @queue failed: @message', [
          '@queue' => $queue_id,
          '@message' => $e->getMessage(),
        ]);
        break;
      }
    }
  }

}
