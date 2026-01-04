<?php

namespace Drupal\hosting\Controller;

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides route redirects for Aegir list pages.
 */
class HostingRedirectController {

  /**
   * Redirects to the configured View route.
   */
  public function redirectToView(string $redirect_route, array $redirect_parameters = [], array $redirect_options = [], int $redirect_status = 302): RedirectResponse {
    $url = Url::fromRoute($redirect_route, $redirect_parameters, $redirect_options);
    return new RedirectResponse($url->toString(), $redirect_status);
  }

}
