<?php

namespace Drupal\hosting_platform\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for comparing packages between two hosting platforms.
 *
 * Replicates the D7 Aegir "Compare platforms" page that shows a side-by-side
 * view of packages on each platform, highlighting version differences,
 * additions, and removals.
 */
class PlatformComparisonController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Renders the platform comparison page.
   *
   * @param int $source
   *   Source platform ID.
   * @param int $target
   *   Target platform ID.
   *
   * @return array
   *   A render array.
   */
  public function compare(int $source, int $target): array {
    $platform_storage = $this->entityTypeManager->getStorage('hosting_platform');

    $source_platform = $platform_storage->load($source);
    $target_platform = $platform_storage->load($target);

    if (!$source_platform || !$target_platform) {
      throw new NotFoundHttpException();
    }

    $source_packages = $this->loadPlatformPackages($source);
    $target_packages = $this->loadPlatformPackages($target);

    // Merge all package names.
    $all_names = array_unique(array_merge(
      array_keys($source_packages),
      array_keys($target_packages),
    ));
    sort($all_names);

    $rows = [];
    foreach ($all_names as $name) {
      $source_version = $source_packages[$name] ?? NULL;
      $target_version = $target_packages[$name] ?? NULL;

      if ($source_version && $target_version) {
        if ($source_version === $target_version) {
          $class = 'pkg-same';
          $status = $this->t('Same');
        }
        elseif (version_compare($target_version, $source_version, '>')) {
          $class = 'pkg-upgraded';
          $status = $this->t('Upgraded');
        }
        else {
          $class = 'pkg-downgraded';
          $status = $this->t('Downgraded');
        }
      }
      elseif ($target_version && !$source_version) {
        $class = 'pkg-added';
        $status = $this->t('Added');
        $source_version = '—';
      }
      else {
        $class = 'pkg-removed';
        $status = $this->t('Removed');
        $target_version = '—';
      }

      $rows[] = [
        'data' => [
          $name,
          $source_version,
          $target_version,
          ['data' => $status, 'class' => [$class]],
        ],
        'class' => [$class],
      ];
    }

    $build = [];
    $build['#prefix'] = '<div class="hosting-platform-compare">';
    $build['#suffix'] = '</div>';

    $build['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Comparing %source and %target', [
        '%source' => $source_platform->label(),
        '%target' => $target_platform->label(),
      ]),
      '#attributes' => ['class' => ['hosting-platform-compare__title']],
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Package'),
        $this->t('%source version', ['%source' => $source_platform->label()]),
        $this->t('%target version', ['%target' => $target_platform->label()]),
        $this->t('Status'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No packages found on either platform.'),
      '#attributes' => ['class' => ['hosting-platform-compare__table']],
    ];

    $build['#attached']['library'][] = 'hosting/common';

    return $build;
  }

  /**
   * Loads package versions for a given platform.
   *
   * @param int $platform_id
   *   The platform entity ID.
   *
   * @return array
   *   Associative array of package_short_name => version.
   */
  protected function loadPlatformPackages(int $platform_id): array {
    $packages = [];

    try {
      $instance_storage = $this->entityTypeManager->getStorage('hosting_package_instance');
      $package_storage = $this->entityTypeManager->getStorage('hosting_package');

      $instance_ids = $instance_storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('platform', $platform_id)
        ->execute();

      if ($instance_ids) {
        $instances = $instance_storage->loadMultiple($instance_ids);
        foreach ($instances as $instance) {
          $version = (string) $instance->get('version')->value;
          $package_id = $instance->get('package')->target_id;
          if ($package_id) {
            $package = $package_storage->load($package_id);
            if ($package) {
              $name = $package->get('short_name')->value ?: (string) $package_id;
              $packages[$name] = $version;
            }
          }
        }
      }
    }
    catch (\Exception) {
      // Entity types may not exist.
    }

    return $packages;
  }

}
