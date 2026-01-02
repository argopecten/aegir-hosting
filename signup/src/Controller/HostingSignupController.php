<?php

namespace Drupal\hosting_signup\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

class HostingSignupController extends ControllerBase {
  /**
   * Render the signup thank-you page.
   */
  public function thanks(Request $request) {
    $url = (string) $request->query->get('url', '');
    $email = (string) $request->query->get('email', '');
    $name = (string) $request->query->get('name', '');

    return [
      '#markup' => $this->t('<h3>Thank you @name</h3>Your site (@url) has been requested, and the moment it is ready you will receive a mail at @email with instructions on how to log into it.', [
        '@name' => $name,
        '@url' => $url,
        '@email' => $email,
      ]),
    ];
  }

}
