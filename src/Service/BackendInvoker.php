<?php

namespace Drupal\hosting\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class BackendInvoker implements BackendInvokerInterface {

  protected ConfigFactoryInterface $configFactory;
  protected LoggerInterface $logger;

  public function __construct(ConfigFactoryInterface $configFactory, LoggerInterface $logger) {
    $this->configFactory = $configFactory;
    $this->logger = $logger;
  }

  public function invoke(string $command, array $args = [], array $options = [], ?string $alias = NULL): array {
    $config = $this->configFactory->get('hosting.settings');
    $drush = $config->get('backend.drush_path') ?: 'drush';
    $alias = $alias ?: $config->get('backend.alias');

    $cmd = [$drush];
    if (!empty($alias)) {
      $cmd[] = $alias;
    }
    $cmd[] = $command;
    foreach ($args as $arg) {
      $cmd[] = $arg;
    }
    foreach ($options as $key => $value) {
      if (is_bool($value)) {
        if ($value) {
          $cmd[] = '--' . $key;
        }
      }
      else {
        $cmd[] = '--' . $key . '=' . $value;
      }
    }

    $process = new Process($cmd);
    $process->setTimeout(NULL);
    $process->run();

    if (!$process->isSuccessful()) {
      $this->logger->error('Backend command failed: @error', ['@error' => $process->getErrorOutput()]);
    }

    return [
      'output' => $process->getOutput(),
      'error' => $process->getErrorOutput(),
      'exit_code' => $process->getExitCode(),
    ];
  }

}
