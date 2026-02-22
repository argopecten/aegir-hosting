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
   *   Result array with output and exit code.
   */
  public function invoke(string $command, array $args = [], array $options = [], ?string $alias = NULL): array;

}
