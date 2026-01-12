<?php

namespace Drupal\hosting_package\Service;

class PackageVersionParser {

  public function toCode(string $version): int {
    $parts = preg_split('/[^0-9]+/', $version);
    $parts = array_values(array_filter($parts, 'strlen'));
    $parts = array_map('intval', $parts);
    $parts = array_pad($parts, 4, 0);

    $major = $parts[0];
    $minor = $parts[1];
    $patch = $parts[2];
    $extra = $parts[3];

    return (int) sprintf('%d%03d%03d%03d', $major, $minor, $patch, $extra);
  }

}
