<?php

namespace Drupal\hosting_task\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\hosting\Service\BackendInvokerInterface;
use Drupal\hosting_task\Entity\HostingTaskInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

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

    // Check if task should be retried later.
    if ($task->getStatus() === 'failed' && $task->get('next_retry_time')->value > 0) {
      if ($task->get('next_retry_time')->value > $this->time->getRequestTime()) {
        // Not yet time to retry.
        return;
      }
    }

    // Check if task is cancelled.
    if ($task->getStatus() === 'cancelled') {
      $this->logger->info('Task @id is cancelled, skipping execution.', ['@id' => $task_id]);
      return;
    }

    $task->setStatus('processing');
    $task->set('started', $this->time->getRequestTime());
    $task->save();

    $args = array_merge([$task->get('context_name')->value], $task->getArgs());
    
    // Use streaming invoke for real-time output.
    $output_buffer = '';
    $error_buffer = '';
    
    $callback = function ($type, $buffer) use (&$output_buffer, &$error_buffer, $task_id) {
      if ($type === Process::OUT) {
        $output_buffer .= $buffer;
        // Log incremental output.
        $this->taskLogManager->log($task_id, 'status', $buffer);
      }
      else {
        $error_buffer .= $buffer;
        $this->taskLogManager->log($task_id, 'error', $buffer, $buffer);
      }
    };
    
    $result = $this->backendInvoker->invokeStreaming(
      $task->getCommand(),
      $args,
      $task->getOptions(),
      $callback
    );

    // Store process ID for potential cancellation.
    if (!empty($result['pid'])) {
      $task->set('process_id', $result['pid']);
      $task->save();
    }

    if (!empty($result['exit_code'])) {
      // Task failed - check if retry is needed.
      $this->handleTaskFailure($task, $result);
    }
    else {
      $task->setStatus('success');
      $task->set('retry_count', 0);
      $task->set('next_retry_time', 0);
    }
    
    $task->set('completed', $this->time->getRequestTime());
    $task->set('process_id', 0);
    $task->save();
  }

  /**
   * Handle task failure and determine if retry is needed.
   *
   * @param \Drupal\hosting_task\Entity\HostingTaskInterface $task
   *   The failed task.
   * @param array $result
   *   The backend invocation result.
   */
  protected function handleTaskFailure(HostingTaskInterface $task, array $result): void {
    $retry_count = (int) $task->get('retry_count')->value;
    $max_retries = (int) $task->get('max_retries')->value;

    if ($retry_count < $max_retries) {
      // Calculate exponential backoff: 2^retry_count * 60 seconds.
      $delay = pow(2, $retry_count) * 60;
      $next_retry = $this->time->getRequestTime() + $delay;

      $task->set('retry_count', $retry_count + 1);
      $task->set('next_retry_time', $next_retry);
      $task->setStatus('failed');

      $this->logger->info('Task @id failed, will retry (@count/@max) in @delay seconds', [
        '@id' => $task->id(),
        '@count' => $retry_count + 1,
        '@max' => $max_retries,
        '@delay' => $delay,
      ]);

      // Re-queue the task for retry.
      $this->queueTask($task);
    }
    else {
      // Max retries exceeded.
      $task->setStatus('failed');
      $this->logger->error('Task @id failed permanently after @max retries', [
        '@id' => $task->id(),
        '@max' => $max_retries,
      ]);
    }
  }

  /**
   * Cancel a task.
   *
   * @param int $task_id
   *   Task ID.
   * @param int $user_id
   *   User ID who is cancelling.
   *
   * @return bool
   *   TRUE if cancelled successfully.
   */
  public function cancelTask(int $task_id, int $user_id): bool {
    $storage = $this->entityTypeManager->getStorage('hosting_task');
    $task = $storage->load($task_id);
    
    if (!$task instanceof HostingTaskInterface) {
      return FALSE;
    }

    // Check if task can be cancelled.
    $status = $task->getStatus();
    if (!in_array($status, ['queued', 'processing', 'failed'])) {
      $this->logger->warning('Cannot cancel task @id with status @status', [
        '@id' => $task_id,
        '@status' => $status,
      ]);
      return FALSE;
    }

    // If processing, try to kill the backend process.
    if ($status === 'processing') {
      $pid = (int) $task->get('process_id')->value;
      if ($pid > 0) {
        // Send SIGTERM to the process.
        posix_kill($pid, SIGTERM);
        $this->logger->info('Sent SIGTERM to process @pid for task @id', [
          '@pid' => $pid,
          '@id' => $task_id,
        ]);
        
        // Wait a moment, then force kill if still running.
        sleep(2);
        if (posix_kill($pid, 0)) {
          // Process still exists, force kill.
          posix_kill($pid, SIGKILL);
          $this->logger->warning('Sent SIGKILL to process @pid for task @id', [
            '@pid' => $pid,
            '@id' => $task_id,
          ]);
        }
      }
    }

    // Update task status.
    $task->setStatus('cancelled');
    $task->set('cancelled_by', $user_id);
    $task->set('completed', $this->time->getRequestTime());
    $task->set('process_id', 0);
    $task->save();

    $this->taskLogManager->log($task_id, 'status', 'Task cancelled by user ' . $user_id);

    $this->logger->info('Task @id cancelled by user @user', [
      '@id' => $task_id,
      '@user' => $user_id,
    ]);

    return TRUE;
  }

  /**
   * Retry a failed task immediately.
   *
   * @param int $task_id
   *   Task ID.
   *
   * @return bool
   *   TRUE if retry was queued.
   */
  public function retryTask(int $task_id): bool {
    $storage = $this->entityTypeManager->getStorage('hosting_task');
    $task = $storage->load($task_id);
    
    if (!$task instanceof HostingTaskInterface) {
      return FALSE;
    }

    if ($task->getStatus() !== 'failed') {
      return FALSE;
    }

    // Reset retry timing and re-queue.
    $task->set('next_retry_time', 0);
    $task->setStatus('queued');
    $task->save();

    $this->queueTask($task);

    $this->logger->info('Task @id manually retried', ['@id' => $task_id]);

    return TRUE;
  }

}
