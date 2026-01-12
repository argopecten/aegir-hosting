<?php

namespace Drupal\hosting_package\Service;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\File\FileSystemInterface;

class PackageDiscovery {

  protected FileSystemInterface $fileSystem;

  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
  }

  public function discover(string $root): array {
    $packages = [];
    $types = [
      'profile' => 'profiles',
      'module' => 'modules',
      'theme' => 'themes',
    ];

    foreach ($types as $type => $dir) {
      $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $dir;
      if (!is_dir($path)) {
        continue;
      }

      $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
      );

      foreach ($iterator as $fileinfo) {
        if (!$fileinfo->isFile()) {
          continue;
        }
        $filename = $fileinfo->getFilename();
        if (!str_ends_with($filename, '.info.yml')) {
          continue;
        }

        $info = Yaml::decode(file_get_contents($fileinfo->getPathname())) ?? [];
        $short_name = substr($filename, 0, -strlen('.info.yml'));
        $packages[] = [
          'package_type' => $type,
          'short_name' => $short_name,
          'old_short_name' => '',
          'description' => (string) ($info['description'] ?? ''),
          'version' => (string) ($info['version'] ?? ''),
          'path' => $fileinfo->getPath(),
          'info' => $info,
        ];
      }
    }

    return $packages;
  }

}
