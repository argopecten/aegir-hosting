<?php

namespace Drupal\hosting_platform;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Lists Hosting platform entities.
 */
class HostingPlatformListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Platform');
    $header['release'] = $this->t('Release');
    $header['server'] = $this->t('Server');
    $header['verified'] = $this->t('Verified');
    $header['sites'] = $this->t('Sites');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\hosting_platform\Entity\HostingPlatform $entity */
    
    // Platform name with link to canonical view
    $row['label'] = Link::fromTextAndUrl(
      $entity->label(),
      $entity->toUrl('canonical')
    );

    // Release information (Drupal version)
    $release = '';
    if ($entity->hasField('php_version') && !$entity->get('php_version')->isEmpty()) {
      $release = 'drupal ' . $entity->get('php_version')->value;
    }
    $row['release'] = $release;

    // Web server
    $server = '';
    if ($entity->hasField('web_server') && !$entity->get('web_server')->isEmpty()) {
      $server_entity = $entity->get('web_server')->entity;
      if ($server_entity) {
        $server = Link::fromTextAndUrl(
          $server_entity->label(),
          $server_entity->toUrl('canonical')
        );
      }
    }
    $row['server'] = $server;

    // Verified date
    $verified = '';
    if ($entity->hasField('verified') && !$entity->get('verified')->isEmpty()) {
      $verified_timestamp = $entity->get('verified')->value;
      if ($verified_timestamp) {
        $verified = \Drupal::service('date.formatter')->formatInterval(
          \Drupal::time()->getRequestTime() - $verified_timestamp
        ) . ' ' . $this->t('ago');
      }
    }
    $row['verified'] = $verified;

    // Count of sites on this platform
    $site_count = 0;
    if (\Drupal::moduleHandler()->moduleExists('hosting_site')) {
      $query = \Drupal::entityQuery('hosting_site')
        ->condition('platform', $entity->id())
        ->accessCheck(TRUE)
        ->count();
      $site_count = $query->execute();
    }
    
    // Link to sites if there are any
    if ($site_count > 0) {
      $row['sites'] = Link::fromTextAndUrl(
        $site_count,
        Url::fromRoute('view.hosting_site_list.page_1', [], [
          'query' => ['platform' => $entity->id()]
        ])
      );
    } else {
      $row['sites'] = $site_count;
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    
    // Add custom CSS for platform list
    $build['#attached']['library'][] = 'hosting_platform/platform-list';
    
    return $build;
  }

}
