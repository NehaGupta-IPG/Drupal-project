<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the library dependencies of Default Admin.
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class LibraryDependenciesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Tests that the description_toggle library depends on core/drupal.
   *
   * Without the dependency, description_toggle.js can be loaded before
   * drupal.js, which causes "Drupal is not defined" with JS aggregation.
   */
  public function testDescriptionToggleDependsOnCoreDrupal(): void {
    $this->container->get('theme_installer')->install(['default_admin']);
    $libraries = $this->container->get('library.dependency_resolver')
      ->getLibrariesWithDependencies(['default_admin/description_toggle']);
    $this->assertContains('core/drupal', $libraries);
  }

}
