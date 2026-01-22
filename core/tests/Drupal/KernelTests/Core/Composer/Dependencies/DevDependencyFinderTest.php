<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Composer\Dependencies;

use Drupal\Core\Composer\Dependencies\DevDependencyFinder;
use Drupal\Core\Composer\ProjectRootInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @internal
 *
 * @todo Make this a UnitTestBase test when the systems it tests no longer have
 *   dependencies on install.inc.
 * @see https://www.drupal.org/node/2909480
 */
#[Group('Composer')]
#[Group('Dependencies')]
#[CoversClass(DevDependencyFinder::class)]
#[CoversMethod(DevDependencyFinder::class, 'buildRequirements')]
#[RunTestsInSeparateProcesses]
class DevDependencyFinderTest extends KernelTestBase {

  /**
   * Data provider for ::testBuildRequirements())
   */
  public static function provideBuildRequirements(): array {
    return [
      'no_dev_install' => [RequirementSeverity::OK, FALSE],
      'dev_install' => [RequirementSeverity::Warning, TRUE],
    ];
  }

  #[DataProvider('provideBuildRequirements')]
  public function testBuildRequirements($expected, $isDevInstall): void {
    $mock_root = $this->getMockBuilder(ProjectRootInterface::class)
      ->onlyMethods(['getProjectVendorDirectory', 'isDevInstall'])
      ->getMock();
    $mock_root->expects($this->once())
      ->method('isDevInstall')
      ->willReturn($isDevInstall);

    $finder = new DevDependencyFinder(
      $mock_root,
      $this->getMockBuilder(TranslationInterface::class)->getMock()
    );
    $requirements = $finder->buildRequirements();

    $this->assertArrayHasKey('composer_dev_dependencies', $requirements);
    foreach (['title', 'description', 'severity'] as $property) {
      $this->assertArrayHasKey($property, $requirements['composer_dev_dependencies']);
    }
    $this->assertEquals($expected, $requirements['composer_dev_dependencies']['severity']);
  }

}
