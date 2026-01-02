<?php

namespace Drupal\hosting_site\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\hosting_platform\Entity\HostingPlatform;
use Drupal\hosting_site\Entity\HostingSite;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class HostingSiteController extends ControllerBase {
  /**
   * Redirect to the site add form scoped to a platform.
   */
  public function addByPlatform(HostingPlatform $hosting_platform) {
    $url = Url::fromRoute('entity.hosting_site.add_form', [], [
      'query' => ['platform' => $hosting_platform->id()],
    ]);
    return new RedirectResponse($url->toString());
  }

  /**
   * Ajax helper for site form options.
   */
  public function formCheck(Request $request) {
    $platform = $request->query->get('platform');
    $values = $request->request->all();
    if (isset($values['site_language']) && !isset($values['language'])) {
      $values['language'] = $values['site_language'];
    }

    return new JsonResponse(hosting_site_available_options($values, $platform));
  }

  /**
   * Autocomplete for site names.
   */
  public function autocomplete($string) {
    $query = \Drupal::database()->select('hosting_site', 's');
    $results = $query->fields('s', ['id', 'label'])
      ->condition('s.label', '%' . \Drupal::database()->escapeLike($string) . '%', 'LIKE')
      ->condition('s.status', HOSTING_SITE_DELETED, '!=')
      ->execute();

    $matches = [];
    foreach ($results as $row) {
      $matches[$row->label] = \Drupal\Component\Utility\Html::escape($row->label);
    }

    return new JsonResponse($matches);
  }

  /**
   * Redirect to a site's login or front page.
   */
  public function gotoSite(HostingSite $hosting_site) {
    return hosting_site_goto($hosting_site);
  }

}
