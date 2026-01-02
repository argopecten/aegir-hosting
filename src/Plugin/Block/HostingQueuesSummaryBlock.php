<?php

namespace Drupal\hosting\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a Hosting queues summary block.
 *
 * @Block(
 *   id = "hosting_queues_summary",
 *   admin_label = @Translation("Hosting queues summary"),
 *   category = @Translation("Hosting")
 * )
 */
class HostingQueuesSummaryBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $output = hosting_queue_summary_block();
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
    return AccessResult::allowedIfHasPermission($account, 'administer hosting queues');
  }

}
