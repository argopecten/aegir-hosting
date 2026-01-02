<?php

namespace Drupal\hosting\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a Hosting queues block.
 *
 * @Block(
 *   id = "hosting_queues",
 *   admin_label = @Translation("Hosting queues"),
 *   category = @Translation("Hosting")
 * )
 */
class HostingQueuesBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $output = hosting_queue_block();
    if (!$output) {
      return [];
    }

    return [
      '#markup' => Markup::create($output),
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access task logs');
  }

}
