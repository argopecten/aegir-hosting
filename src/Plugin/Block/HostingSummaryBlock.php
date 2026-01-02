<?php

namespace Drupal\hosting\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a Hosting summary block.
 *
 * @Block(
 *   id = "hosting_summary",
 *   admin_label = @Translation("Hosting summary"),
 *   category = @Translation("Hosting")
 * )
 */
class HostingSummaryBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $summaries = \Drupal::moduleHandler()->invokeAll('hosting_summary');
    $output = '';

    foreach ($summaries as $summary) {
      if (is_array($summary)) {
        foreach ($summary as $item) {
          $output .= $item;
        }
        continue;
      }
      $output .= $summary;
    }

    if ($output === '') {
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
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

}
