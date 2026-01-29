<?php

namespace Drupal\hosting_platform\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hosting_platform\Entity\HostingPlatform;

class PlatformManager {

  /**
   * The base directory where all platforms must be located.
   */
  public const PLATFORMS_BASE_DIR = '/var/aegir/platforms';

  protected EntityTypeManagerInterface $entityTypeManager;
  protected ConfigFactoryInterface $configFactory;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->ensurePlatformsDirectoryExists();
  }

  /**
   * Ensure the platforms base directory exists.
   */
  protected function ensurePlatformsDirectoryExists(): void {
    if (!is_dir(self::PLATFORMS_BASE_DIR)) {
      // Create directory with proper permissions (775 allows www-data group write)
      if (!@mkdir(self::PLATFORMS_BASE_DIR, 0775, TRUE)) {
        \Drupal::logger('hosting_platform')->warning('Failed to create platforms directory: @dir', [
          '@dir' => self::PLATFORMS_BASE_DIR,
        ]);
      }
    }
  }

  public function isValidPublishPath(string $path): bool {
    $path = trim($path);
    if ($path === '') {
      return FALSE;
    }
    if (!str_starts_with($path, '/')) {
      return FALSE;
    }
    // Must be within the platforms directory
    $real_path = realpath($path);
    $base_dir = realpath(self::PLATFORMS_BASE_DIR);
    if ($real_path === FALSE || $base_dir === FALSE) {
      return FALSE;
    }
    if (!str_starts_with($real_path, $base_dir)) {
      return FALSE;
    }
    if (!is_dir($path)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Check if a platform path has a valid composer.json file.
   *
   * @param string $path
   *   The absolute path to check.
   *
   * @return bool
   *   TRUE if composer.json exists and is valid, FALSE otherwise.
   */
  public function hasValidComposerJson(string $path): bool {
    $composer_file = rtrim($path, '/') . '/composer.json';
    
    if (!file_exists($composer_file)) {
      return FALSE;
    }
    
    $content = @file_get_contents($composer_file);
    if ($content === FALSE) {
      return FALSE;
    }
    
    $json = json_decode($content, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return FALSE;
    }
    
    // Check if it's a valid Drupal project
    if (!isset($json['type'])) {
      return FALSE;
    }
    
    return TRUE;
  }

  public function isPublishPathUnique(string $path, ?int $exclude_id = NULL): bool {
    $query = $this->entityTypeManager
      ->getStorage('hosting_platform')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('publish_path', $path);

    if ($exclude_id) {
      $query->condition('id', $exclude_id, '<>');
    }

    return $query->count()->execute() == 0;
  }

  public function getDefaultStatus(): int {
    $config = $this->configFactory->get('hosting.settings');
    if ($config->get('settings.lock_platforms_by_default')) {
      return HostingPlatform::STATUS_LOCKED;
    }
    return HostingPlatform::STATUS_QUEUED;
  }

  /**
   * Validate a Git repository URL.
   *
   * @param string $url
   *   The Git repository URL.
   *
   * @return bool
   *   TRUE if the URL is valid, FALSE otherwise.
   */
  public function isValidGitUrl(string $url): bool {
    $url = trim($url);
    if ($url === '') {
      return FALSE;
    }
    
    // Support https and git protocols
    $pattern = '/^(https?:\/\/|git@)(github\.com|gitlab\.com|bitbucket\.org)[\/:].+\.git$|^(https?:\/\/)(github\.com|gitlab\.com|bitbucket\.org)\/.+$/';
    
    return (bool) preg_match($pattern, $url);
  }

  /**
   * Generate a platform path from a Git repository URL.
   *
   * @param string $git_url
   *   The Git repository URL.
   *
   * @return string
   *   The suggested platform path.
   */
  public function generatePlatformPathFromGitUrl(string $git_url): string {
    // Extract repository name from URL
    $path_parts = parse_url($git_url, PHP_URL_PATH);
    if (!$path_parts) {
      return self::PLATFORMS_BASE_DIR . '/' . uniqid('platform_');
    }
    
    $repo_name = basename($path_parts, '.git');
    $repo_name = preg_replace('/[^a-z0-9_-]/i', '_', $repo_name);
    
    // Add timestamp to make it unique
    $platform_dir = $repo_name . '_' . time();
    
    return self::PLATFORMS_BASE_DIR . '/' . $platform_dir;
  }

  /**
   * Clone a Git repository to a platform path.
   *
   * @param string $git_url
   *   The Git repository URL.
   * @param string $target_path
   *   The target path to clone into.
   *
   * @return array
   *   Array with 'success' boolean and 'message' string.
   */
  public function cloneGitRepository(string $git_url, string $target_path): array {
    // Ensure parent directory exists with proper permissions
    $parent_dir = dirname($target_path);
    if (!is_dir($parent_dir)) {
      if (!@mkdir($parent_dir, 0775, TRUE)) {
        return [
          'success' => FALSE,
          'message' => 'Failed to create parent directory: ' . $parent_dir . '. Check permissions.',
        ];
      }
    }
    
    // Verify parent directory is writable
    if (!is_writable($parent_dir)) {
      return [
        'success' => FALSE,
        'message' => 'Parent directory is not writable: ' . $parent_dir . '. Check permissions.',
      ];
    }
    
    // Prevent cloning if directory already exists
    if (file_exists($target_path)) {
      return [
        'success' => FALSE,
        'message' => 'Target directory already exists.',
      ];
    }
    
    // Execute git clone
    $escaped_url = escapeshellarg($git_url);
    $escaped_path = escapeshellarg($target_path);
    $command = "git clone {$escaped_url} {$escaped_path} 2>&1";
    
    exec($command, $output, $return_var);
    
    if ($return_var !== 0) {
      return [
        'success' => FALSE,
        'message' => 'Git clone failed: ' . implode("\n", $output),
      ];
    }
    
    // Run composer install if composer.json exists
    $composer_file = $target_path . '/composer.json';
    if (file_exists($composer_file)) {
      $old_dir = getcwd();
      chdir($target_path);
      exec('composer install --no-interaction 2>&1', $composer_output, $composer_return);
      chdir($old_dir);
      
      if ($composer_return !== 0) {
        return [
          'success' => FALSE,
          'message' => 'Composer install failed: ' . implode("\n", $composer_output),
        ];
      }
    }
    
    return [
      'success' => TRUE,
      'message' => 'Platform cloned successfully.',
    ];
  }

}
