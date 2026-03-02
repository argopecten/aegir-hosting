<?php

namespace Drupal\hosting\Service;

use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Provides the URL to the hosting icon sprite SVG.
 *
 * Centralizes icon sprite path resolution so modules don't hardcode
 * theme names. Uses the active theme's path at runtime.
 */
class HostingIconProvider {

  public function __construct(
    protected readonly ThemeManagerInterface $themeManager,
  ) {}

  /**
   * Returns the full URL to the SVG icon sprite.
   */
  public function getSpriteUrl(): string {
    $path = $this->themeManager->getActiveTheme()->getPath();
    return base_path() . $path . '/images/svg/aegir-icons-sprite.svg';
  }

}
