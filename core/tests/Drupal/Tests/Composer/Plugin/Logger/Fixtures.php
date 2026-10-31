<?php

declare(strict_types=1);

namespace Drupal\Tests\Composer\Plugin\Logger;

use Drupal\Tests\Composer\Plugin\FixturesBase;

/**
 * Generates fixtures for testing the Composer installer logger plugin.
 */
final class Fixtures extends FixturesBase {

  /**
   * {@inheritdoc}
   */
  public function projectRoot(): string {
    return realpath(__DIR__) . '/../../../../../../../composer/Plugin/Logger';
  }

  /**
   * {@inheritdoc}
   */
  public function allFixturesDir(): string {
    return realpath(__DIR__ . '/fixtures');
  }

  /**
   * {@inheritdoc}
   */
  public function tmpDir(string $prefix): string {
    $prefix .= static::persistentPrefix();
    $tmpDir = sys_get_temp_dir() . '/logger-' . $prefix . uniqid(md5($prefix . microtime()), TRUE);
    $this->tmpDirs[] = $tmpDir;
    return $tmpDir;
  }

}
