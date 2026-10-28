<?php

declare(strict_types=1);

namespace Drupal\Tests\Composer;

use Drupal\Composer\Composer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Composer\Composer.
 */
#[CoversClass(Composer::class)]
#[Group('Composer')]
class ComposerTest extends UnitTestCase {

  /**
   * Verify that Composer::ensureComposerVersion() doesn't break.
   */
  #[DoesNotPerformAssertions]
  public function testEnsureComposerVersion(): void {
    try {
      Composer::ensureComposerVersion();
    }
    catch (\RuntimeException $e) {
      $this->assertMatchesRegularExpression('/Drupal core development requires Composer 2.3.5, but Composer /', $e->getMessage());
    }
  }

  /**
   * Ensure that the configured php version matches the minimum php version.
   *
   * Also ensure that the minimum php version in the root-level composer.json
   * file exactly matches \Drupal::MINIMUM_PHP.
   */
  public function testEnsurePhpConfiguredVersion(): void {
    $composer_json = json_decode(file_get_contents($this->root . '/composer.json'), TRUE);
    $composer_core_json = json_decode(file_get_contents($this->root . '/core/composer.json'), TRUE);
    $this->assertEquals(\Drupal::MINIMUM_PHP, $composer_json['config']['platform']['php'], 'The \Drupal::MINIMUM_PHP constant should always be exactly the same as the config.platform.php in the root composer.json.');
    $this->assertEquals($composer_core_json['require']['php'], '>=' . $composer_json['config']['platform']['php'], 'The config.platform.php configured version in the root composer.json file should always be exactly the same as the minimum php version configured in core/composer.json.');
  }

  /**
   * Ensure that the minimum php version in core/install.php is up to date.
   *
   * The installer hardcodes the version because it checks it before the
   * autoloader and \Drupal are available.
   */
  public function testInstallerPhpVersion(): void {
    $install_php = file_get_contents($this->root . '/core/install.php');
    $this->assertStringContainsString("version_compare(PHP_VERSION, '" . \Drupal::MINIMUM_PHP . "')", $install_php, 'The minimum php version in core/install.php should always be exactly the same as \Drupal::MINIMUM_PHP.');
  }

}
