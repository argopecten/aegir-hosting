<?php

namespace Drupal\hosting_client\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class HostingClientAutocompleteController extends ControllerBase {

  public function handle(Request $request): JsonResponse {
    $matches = [];
    $string = (string) $request->query->get('q');
    if ($string === '') {
      return new JsonResponse($matches);
    }

    $storage = $this->entityTypeManager()->getStorage('hosting_client');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->range(0, 10);
    $or = $query->orConditionGroup()
      ->condition('name', $string, 'CONTAINS')
      ->condition('uname', $string, 'CONTAINS');
    $query->condition($or);

    $ids = $query->execute();
    if (!$ids) {
      return new JsonResponse($matches);
    }

    $clients = $storage->loadMultiple($ids);
    foreach ($clients as $client) {
      $label = $client->label();
      $uname = (string) $client->get('uname')->value;
      $matches[] = [
        'value' => $label,
        'label' => $uname ? $label . ' (' . $uname . ')' : $label,
      ];
    }

    return new JsonResponse($matches);
  }

}
