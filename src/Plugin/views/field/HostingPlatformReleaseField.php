<?php

namespace Drupal\hosting\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\hosting_platform\Entity\HostingPlatform;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Release field for hosting platforms.
 *
 * @ViewsField("hosting_platform_release")
 */
class HostingPlatformReleaseField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $platform_id = $this->getValue($values);
    if (!$platform_id) {
      return '';
    }

    $platform = $values->_entity instanceof HostingPlatform
      ? $values->_entity
      : \Drupal::entityTypeManager()->getStorage('hosting_platform')->load($platform_id);

    if (!$platform) {
      return '';
    }

    $iid = \Drupal::database()->query(
      "SELECT iid FROM {hosting_package_instance} i
       LEFT JOIN {hosting_package} p ON p.id = i.package_id
       WHERE p.package_type = :package_type AND i.platform = :platform",
      [
        ':package_type' => 'platform',
        ':platform' => $platform->id(),
      ]
    )->fetchField();
    if (!$iid) {
      return '';
    }

    $release = hosting_package_instance_load($iid);
    if (!$release) {
      return '';
    }

    $title = trim(($release->title ?? $release->short_name ?? '') . ' ' . ($release->version ?? ''));
    if ($title === '') {
      return '';
    }

    if (!empty($release->package_id)) {
      return Link::fromTextAndUrl(
        $title,
        Url::fromRoute('entity.hosting_package.canonical', ['hosting_package' => $release->package_id])
      )->toString();
    }

    return $title;
  }

}
