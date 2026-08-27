<?php

declare(strict_types=1);

namespace Drupal\BuildTests\Distribution;

use Drupal\BuildTests\Framework\BuildTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;

// cspell:ignore worktree

/**
 * Tests the Drupal core distribution package.
 */
#[Group('Distribution')]
class DistributionTest extends BuildTestBase {

  /**
   * Tests a Functional test can run against a distribution package.
   */
  public function testFunctional(): void {
    $this->createDistributionProject();
    $this->runTest('Drupal\\Tests\\distribution_test\\Functional\\DistributionFunctionalTest');
  }

  /**
   * Tests a FunctionalJavascript test can run against a distribution package.
   */
  public function testFunctionalJavascript(): void {
    $this->createDistributionProject();
    $this->runTest('Drupal\\Tests\\distribution_test\\FunctionalJavascript\\DistributionFunctionalJavascriptTest');
  }

  /**
   * Creates a project with core installed from a distribution archive.
   */
  private function createDistributionProject(): void {
    $this->copyCodebase();

    $project_path = $this->getWorkspaceDirectory();
    $core_path = $project_path . '/core';
    (new Filesystem())->remove($core_path);
    mkdir($core_path);
    $this->executeCommand('git archive --worktree-attributes --format=tar HEAD:core | tar -xf - -C core');
    $this->assertCommandSuccessful();

    $fixture_path = $this->root . '/core/tests/fixtures/distribution_test/modules/distribution_test';
    (new Filesystem())->mirror($fixture_path, $project_path . '/modules/custom/distribution_test');
  }

  /**
   * Runs a test class in the distribution project.
   */
  private function runTest(string $class): void {
    $this->standUpServer();
    $database_path = $this->getWorkspaceDirectory() . '/sites/default/files/distribution_test.sqlite';
    $database_url = 'sqlite://localhost/' . $database_path . '?module=sqlite';
    $base_url = 'http://localhost:' . $this->getPortNumber();
    $test_path = str_replace('\\', '/', str_replace('Drupal\\Tests\\distribution_test\\', 'modules/custom/distribution_test/tests/src/', $class)) . '.php';
    $this->executeCommand(sprintf(
      'SIMPLETEST_DB=%s SIMPLETEST_BASE_URL=%s php vendor/phpunit/phpunit/phpunit --bootstrap core/tests/bootstrap.php %s',
      escapeshellarg($database_url),
      escapeshellarg($base_url),
      escapeshellarg($test_path),
    ));
    $this->assertCommandSuccessful();
  }

}
