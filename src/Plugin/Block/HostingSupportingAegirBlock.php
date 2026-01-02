<?php

namespace Drupal\hosting\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a Support Aegir block.
 *
 * @Block(
 *   id = "hosting_supporting_aegir",
 *   admin_label = @Translation("Support Aegir"),
 *   category = @Translation("Hosting")
 * )
 */
class HostingSupportingAegirBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $output = hosting_supporting_aegir_block();
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
    return AccessResult::allowedIfHasPermission($account, 'administer hosting');
  }

}
