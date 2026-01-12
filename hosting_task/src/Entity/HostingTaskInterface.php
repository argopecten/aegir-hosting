<?php

namespace Drupal\hosting_task\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

interface HostingTaskInterface extends ContentEntityInterface {

  public function getTaskType(): string;

  public function getStatus(): string;

  public function setStatus(string $status): self;

  public function getCommand(): string;

  public function getArgs(): array;

  public function getOptions(): array;

}
