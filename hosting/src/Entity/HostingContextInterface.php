<?php

namespace Drupal\hosting\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

interface HostingContextInterface extends ContentEntityInterface {

  public function getContextName(): string;

  public function getEntityTypeId(): string;

  public function getEntityId(): int;

}
