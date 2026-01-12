<?php

namespace Drupal\hosting_site\Service;

class DomainValidator {

  public function normalize(string $domain): string {
    return strtolower(trim($domain));
  }

  public function isValid(string $domain): bool {
    $domain = $this->normalize($domain);
    if ($domain === '') {
      return FALSE;
    }
    if (!str_contains($domain, '.')) {
      return FALSE;
    }
    return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== FALSE;
  }

}
