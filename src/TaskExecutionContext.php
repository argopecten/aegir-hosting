<?php

namespace Drupal\hosting;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Task execution context.
 *
 * This class holds the runtime context for task execution, separating
 * execution state from the persistent task entity. This avoids the need
 * for dynamic properties on entities (PHP 8.2+ compatibility).
 */
class TaskExecutionContext {

  /**
   * The task entity being executed.
   */
  public ContentEntityInterface $task;

  /**
   * The reference entity (site, platform, server, etc.) for the task.
   */
  public ?ContentEntityInterface $ref = NULL;

  /**
   * Task execution options.
   */
  public array $options = [];

  /**
   * Context options to pass to provision backend.
   */
  public array $context_options = [];

  /**
   * Task arguments.
   */
  public array $args = [];

  /**
   * Task type information from hook_hosting_tasks().
   */
  public ?array $task_info = NULL;

  /**
   * The provision command to execute.
   */
  public ?string $task_command = NULL;

  /**
   * Task output from backend execution.
   */
  private array $output = [];

  /**
   * Constructs a TaskExecutionContext.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $task
   *   The task entity.
   */
  public function __construct(ContentEntityInterface $task) {
    $this->task = $task;
  }

  /**
   * Gets the task entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The task entity.
   */
  public function getTask(): ContentEntityInterface {
    return $this->task;
  }

  /**
   * Gets the reference entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The reference entity or NULL.
   */
  public function getRef(): ?ContentEntityInterface {
    return $this->ref;
  }

  /**
   * Sets the reference entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $ref
   *   The reference entity.
   */
  public function setRef(?ContentEntityInterface $ref): void {
    $this->ref = $ref;
  }

  /**
   * Gets task execution options.
   *
   * @return array
   *   The options array.
   */
  public function getOptions(): array {
    return $this->options;
  }

  /**
   * Sets task execution options.
   *
   * @param array $options
   *   The options array.
   */
  public function setOptions(array $options): void {
    $this->options = $options;
  }

  /**
   * Gets context options for provision backend.
   *
   * @return array
   *   The context options array.
   */
  public function getContextOptions(): array {
    return $this->context_options;
  }

  /**
   * Sets context options for provision backend.
   *
   * @param array $context_options
   *   The context options array.
   */
  public function setContextOptions(array $context_options): void {
    $this->context_options = $context_options;
  }

  /**
   * Gets task arguments.
   *
   * @return array
   *   The arguments array.
   */
  public function getArgs(): array {
    return $this->args;
  }

  /**
   * Sets task arguments.
   *
   * @param array $args
   *   The arguments array.
   */
  public function setArgs(array $args): void {
    $this->args = $args;
  }

  /**
   * Gets task type information.
   *
   * @return array|null
   *   The task info array or NULL.
   */
  public function getTaskInfo(): ?array {
    return $this->task_info;
  }

  /**
   * Sets task type information.
   *
   * @param array|null $task_info
   *   The task info array.
   */
  public function setTaskInfo(?array $task_info): void {
    $this->task_info = $task_info;
  }

  /**
   * Gets the task command.
   *
   * @return string|null
   *   The task command or NULL.
   */
  public function getTaskCommand(): ?string {
    return $this->task_command;
  }

  /**
   * Sets the task command.
   *
   * @param string|null $task_command
   *   The task command.
   */
  public function setTaskCommand(?string $task_command): void {
    $this->task_command = $task_command;
  }

  /**
   * Gets the output from task execution.
   *
   * @return array
   *   The output array.
   */
  public function getOutput(): array {
    return $this->output;
  }

  /**
   * Sets the output from task execution.
   *
   * @param array $output
   *   The output array.
   */
  public function setOutput(array $output): void {
    $this->output = $output;
  }

}
