<?php

namespace Drupal\hosting\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\hosting\Service\QueueDispatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HostingQueuesController extends ControllerBase {

  protected QueueDispatcher $dispatcher;
  protected DateFormatterInterface $dateFormatter;

  public function __construct(QueueDispatcher $dispatcher, DateFormatterInterface $dateFormatter) {
    $this->dispatcher = $dispatcher;
    $this->dateFormatter = $dateFormatter;
  }

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('hosting.queue_dispatcher'),
      $container->get('date.formatter')
    );
  }

  public function listing(): array {
    $queues = $this->dispatcher->getQueuesWithState();
    $rows = [];
    foreach ($queues as $queue_id => $queue) {
      $rows[] = [
        'data' => [
          $queue['label'],
          $queue['type'],
          $queue['enabled'] ? $this->t('Enabled') : $this->t('Disabled'),
          $queue['calc_frequency'] ? $this->t('Every @interval', ['@interval' => $this->dateFormatter->formatInterval($queue['calc_frequency'])]) : $this->t('N/A'),
          $queue['calc_items'],
          $queue['total_items'],
          $queue['last_run'] ? $this->dateFormatter->format($queue['last_run'], 'short') : $this->t('Never'),
          $queue['next_run'] ? $this->dateFormatter->format($queue['next_run'], 'short') : $this->t('N/A'),
        ],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Queue'),
        $this->t('Type'),
        $this->t('Status'),
        $this->t('Frequency'),
        $this->t('Items per run'),
        $this->t('Total items'),
        $this->t('Last run'),
        $this->t('Next run'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No queues configured.'),
    ];
  }

  public function disabled(): array {
    return ['#markup' => $this->t('This site has been disabled by the administrators.')];
  }

  public function maintenance(): array {
    return ['#markup' => $this->t('This site is currently in maintenance. Check back later.')];
  }

}
