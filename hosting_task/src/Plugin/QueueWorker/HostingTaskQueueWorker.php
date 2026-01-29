<?php

namespace Drupal\hosting_task\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\hosting_task\Service\TaskManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Processes hosting tasks from the queue.
 *
 * @QueueWorker(
 *   id = "hosting_task",
 *   title = @Translation("Hosting task queue"),
 *   cron = {"time" = 60}
 * )
 */
class HostingTaskQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  protected TaskManagerInterface $taskManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, TaskManagerInterface $taskManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->taskManager = $taskManager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('hosting.task_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!isset($data['task_id'])) {
      return;
    }
    
    // TaskManager now handles retry logic, streaming output, and cancellation.
    $this->taskManager->runTaskId((int) $data['task_id']);
  }

}
