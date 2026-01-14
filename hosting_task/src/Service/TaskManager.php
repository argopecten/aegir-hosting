<?php

namespace Drupal\hosting_task\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\hosting\Service\BackendInvokerInterface;
use Drupal\hosting_task\Entity\HostingTaskInterface;
use Psr\Log\LoggerInterface;

class TaskManager implements TaskManagerInterface {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected QueueFactory $queueFactory;
  protected TimeInterface $time;
  protected LoggerInterface $logger;
  protected BackendInvokerInterface $backendInvoker;
  protected TaskLogManager $taskLogManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, QueueFactory $queueFactory, TimeInterface $time, LoggerInterface $logger, BackendInvokerInterface $backendInvoker, TaskLogManager $taskLogManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->queueFactory = $queueFactory;
    $this->time = $time;
    $this->logger = $logger;
    $this->backendInvoker = $backendInvoker;
    $this->taskLogManager = $taskLogManager;
  }

  public function createTask(string $context_name, string $task_type, array $args = [], array $options = []): HostingTaskInterface {
    $storage = $this->entityTypeManager->getStorage('hosting_task');
    $task = $storage->create([
      'label' => sprintf('%s %s', $task_type, $context_name),
      'task_type' => $task_type,
      'status' => 'queued',
      'command' => 'provision-' . $task_type,
      'args' => json_encode($args),
      'options' => json_encode($options),
      'context_name' => $context_name,
      'started' => 0,
      'completed' => 0,
    ]);
    $task->save();

    $this->queueTask($task);

    return $task;
  }

  public function queueTask(HostingTaskInterface $task): void {
    $queue = $this->queueFactory->get('hosting_task');
    $queue->createItem(['task_id' => $task->id()]);
  }

  public function runTaskId(int $task_id): void {
    $storage = $this->entityTypeManager->getStorage('hosting_task');
    $task = $storage->load($task_id);
    if (!$task instanceof HostingTaskInterface) {
      $this->logger->warning('Hosting task @id not found.', ['@id' => $task_id]);
      return;
    }

    $task->setStatus('processing');
    $task->set('started', $this->time->getRequestTime());
    $task->save();

    $args = array_merge([$task->get('context_name')->value], $task->getArgs());
    $result = $this->backendInvoker->invoke(
      $task->getCommand(),
      $args,
      $task->getOptions()
    );

    $output = $result['output'] ?? '';
    $error = $result['error'] ?? '';
    if ($output !== '') {
      $this->taskLogManager->log($task_id, 'status', $output);
    }
    if ($error !== '') {
      $this->taskLogManager->log($task_id, 'error', $error, $error);
    }

    if (!empty($result['exit_code'])) {
      $task->setStatus('failed');
    }
    else {
      $task->setStatus('success');
    }
    $task->set('completed', $this->time->getRequestTime());
    $task->save();
  }

}
