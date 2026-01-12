<?php

namespace Drupal\hosting_server\Service;

class IpManager {

  public function isValid(string $ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP) !== FALSE;
  }

}
