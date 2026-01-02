<?php

namespace Drupal\hosting_ssl\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\hosting_server\Entity\HostingServer;

class HostingSslController extends ControllerBase {
  /**
   * List certificates installed on a server.
   */
  public function certList(HostingServer $hosting_server) {
    return hosting_ssl_server_cert_list($hosting_server);
  }

}
