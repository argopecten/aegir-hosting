<?php

namespace Drupal\hosting\Service;

interface BackendInvokerInterface {

  /**
   * Invoke a backend drush command.
   *
   * @param string $command
   *   The drush command name.
   * @param array $args
   *   Positional arguments.
   * @param array $options
   *   Options for the drush command.
   * @param string|null $alias
   *   Optional drush alias.
   *
   * @return array
   *   Result array with output, error, exit_code, pid.
   */
  public function invoke(string $command, array $args = [], array $options = [], ?string $alias = NULL): array;

  /**
   * Invoke a backend drush command with streaming output.
   *
   * @param string $command
   *   The drush command name.
   * @param array $args
   *   Positional arguments.
   * @param array $options
   *   Options for the drush command.
   * @param callable|null $callback
   *   Callback receiving output chunks: function(string $type, string $data).
   * @param string|null $alias
   *   Optional drush alias.
   *
   * @return array
   *   Result array with output, error, exit_code, pid.
   */
  public function invokeStreaming(string $command, array $args = [], array $options = [], ?callable $callback = NULL, ?string $alias = NULL): array;

}
