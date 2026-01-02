<?php

namespace Drupal\hosting_client\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\hosting_client\Entity\HostingClient;
use Symfony\Component\HttpFoundation\JsonResponse;

class HostingClientController extends ControllerBase {
  /**
   * Build a site add form scoped to a client.
   */
  public function siteAdd(HostingClient $hosting_client) {
    $site = $this->entityTypeManager()->getStorage('hosting_site')->create([
      'client' => $hosting_client->id(),
    ]);

    return $this->entityFormBuilder()->getForm($site, 'add');
  }

  /**
   * Provide client autocomplete suggestions.
   */
  public function autocomplete($type, $keyword) {
    $matches = [];

    if ($type === 'client') {
      $query = \Drupal::database()->select('hosting_client', 'c');
      $query->addField('c', 'label');
      $query->condition('label', '%' . \Drupal::database()->escapeLike($keyword) . '%', 'LIKE');
      $result = $query->execute();
      foreach ($result as $client) {
        $matches[$client->label] = $client->label;
      }
    }

    return new JsonResponse($matches);
  }

}
