<?php

declare(strict_types=1);

namespace Drupal\Tests\Composer\Plugin\Logger\Functional;

use Composer\InstalledVersions;
use Drupal\BuildTests\Framework\BuildTestBase;
use Drupal\Tests\Composer\Plugin\ExecTrait;
use Drupal\Tests\Composer\Plugin\Logger\Fixtures;

/**
 * Tests the install logger plugin,
 *
 * @group InstallLogger
 */
final class LoggerPluginTest extends BuildTestBase {

  use ExecTrait;

  private string $fixturesDir;

  private Fixtures $fixtures;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fixtures = new Fixtures();
    $this->fixtures->createIsolatedComposerCacheDir();
    $this->fixturesDir = $this->fixtures->tmpDir($this->name());
    $replacements = [
      'PROJECT_ROOT' => $this->fixtures->projectRoot(),
      'COMPOSER_INSTALLERS' => InstalledVersions::getInstallPath('composer/installers'),
    ];
    $this->fixtures->cloneFixtureProjects($this->fixturesDir, $replacements);

    $this->runComposer('install', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Remove any temporary directories that were created.
    $this->fixtures->tearDown();
    parent::tearDown();
  }

  /**
   * @param bool $dry_run
   *
   * @testWith [true]
   *   [false]
   */
  public function testInstallIsLogged(bool $dry_run) {
    $this->runComposer('require fixtures/module-a:^1', $dry_run);

    $operations = require $this->fixturesDir . '/composer-root/installer-log.php';
    $this->assertIsArray($operations);
    $this->assertCount(1, $operations);
    $this->assertSame('install', $operations[0]['operation']);
    $this->assertSame('fixtures/module-a', $operations[0]['package']['name']);
    $this->assertSame('1.0.0', $operations[0]['package']['version']);
  }

  /**
   * @param bool $dry_run
   *
   * @testWith [true]
   *   [false]
   */
  public function testUpdateIsLogged(bool $dry_run): void {
    // The package to update has to actually be in the lock file.
    $this->runComposer('require fixtures/module-a:^1', FALSE);
    $this->runComposer('config repo.module-a path ../module-a-1.1', FALSE);
    $out = $this->runComposer('update fixtures/module-a', $dry_run);

    $operations = require $this->fixturesDir . '/composer-root/installer-log.php';
    $this->assertIsArray($operations);
    $this->assertCount(1, $operations);
    $this->assertSame('update', $operations[0]['operation']);
    $this->assertSame('fixtures/module-a', $operations[0]['from_version']['name']);
    $this->assertSame('1.0.0', $operations[0]['from_version']['version']);
    $this->assertSame('fixtures/module-a', $operations[0]['to_version']['name']);
    $this->assertSame('1.1.0', $operations[0]['to_version']['version']);
  }

  /**
   * Executes a Composer command with standard options.
   *
   * @param string $command
   *   The composer command to execute.
   *
   * @return string
   *   Standard output from the command.
   */
  private function runComposer(string $command, bool $dry_run): string {
    $command = "composer $command --no-interaction --no-ansi";
    if ($dry_run) {
      $command .= " --dry-run";
    }
    return $this->mustExec($command, $this->fixturesDir . '/composer-root', []);
  }

}
