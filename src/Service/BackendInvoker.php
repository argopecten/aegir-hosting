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

  /**
   * Build the environment variables for the backend process.
   *
   * Sets DRUSH_SITE_ALIAS_PATH so the provision backend writes
   * YAML alias files under the project directory.
   */
  protected function buildEnv(): array {
    $config = $this->configFactory->get('hosting.settings');
    $aliasPath = $config->get('backend.alias_path');
    if (empty($aliasPath)) {
      // Default: <app_root>/../drush/sites/aegir
      $aliasPath = $this->projectRoot() . '/drush/sites/aegir';
    }
    return ['DRUSH_SITE_ALIAS_PATH' => $aliasPath];
  }

  /**
   * Resolve the absolute path to the drush binary.
   *
   * Falls back to <project_root>/vendor/bin/drush when no config is set.
   */
  protected function drushPath(): string {
    $configured = $this->configFactory->get('hosting.settings')->get('backend.drush_path');
    if (!empty($configured)) {
      return $configured;
    }
    return $this->projectRoot() . '/vendor/bin/drush';
  }

  /**
   * Return the project root directory (parent of the Drupal web root).
   */
  protected function projectRoot(): string {
    return dirname(\Drupal::root());
  }

  public function invoke(string $command, array $args = [], array $options = [], ?string $alias = NULL): array {
    $config = $this->configFactory->get('hosting.settings');
    $drush = $this->drushPath();
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
    $process->setEnv($this->buildEnv());
    $process->run();

    if (!$process->isSuccessful()) {
      $this->logger->error('Backend command failed: @error', ['@error' => $process->getErrorOutput()]);
    }

    return [
      'output' => $process->getOutput(),
      'error' => $process->getErrorOutput(),
      'exit_code' => $process->getExitCode(),
      'pid' => $process->getPid(),
    ];
  }

  /**
   * Invoke with streaming output callback.
   *
   * @param string $command
   *   Command name.
   * @param array $args
   *   Arguments.
   * @param array $options
   *   Options.
   * @param callable|null $callback
   *   Callback function receiving output chunks: function(string $type, string $data).
   *   $type is either 'out' or 'err'.
   * @param string|null $alias
   *   Server alias.
   *
   * @return array
   *   Result array with output, error, exit_code, pid.
   */
  public function invokeStreaming(string $command, array $args = [], array $options = [], ?callable $callback = NULL, ?string $alias = NULL): array {
    $config = $this->configFactory->get('hosting.settings');
    $drush = $this->drushPath();
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
    $process->setEnv($this->buildEnv());

    // Run with callback for streaming output.
    if ($callback) {
      $process->run($callback);
    }
    else {
      $process->run();
    }

    if (!$process->isSuccessful()) {
      $this->logger->error('Backend command failed: @error', ['@error' => $process->getErrorOutput()]);
    }

    return [
      'output' => $process->getOutput(),
      'error' => $process->getErrorOutput(),
      'exit_code' => $process->getExitCode(),
      'pid' => $process->getPid(),
    ];
  }

}
