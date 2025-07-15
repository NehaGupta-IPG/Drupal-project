<?php

namespace Drupal\KernelTests\Core\Composer\Dependencies;

use Drupal\Core\Composer\Dependencies\DevDependencyFinder;
use Drupal\Core\Composer\ProjectRootInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\Core\Composer\Dependencies\DevDependencyFinder
 * @group Composer
 * @group Dependencies
 *
 * @internal
 *
 * @todo Make this a UnitTestBase test when the systems it tests no longer have
 *   dependencies on install.inc.
 * @see https://www.drupal.org/node/2909480
 */
class DevDependencyFinderTest extends KernelTestBase {

  public function provideBuildRequirements() {
    // Data provider methods are run before setUp(), so they can't use
    // constants from *.inc files. REQUIREMENT_OK = 0, REQUIREMENT_WARNING = 1.
    return [
      'no_dev_no_install' => [0, [], []],
      'no_dev_requirements' => [0, ['any_requirement' => '0.0.1'], []],
      'dev_requirements_installed' => [
        1,
        ['dev_requirement' => '0.0.1'],
        ['dev_requirement' => '0.0.*'],
      ],
      'dev_package_installed' => [
        1,
        [
          'dev_requirement' => '999999-dev',
          'dev_requirement_2' => '1.0.3',
        ],
        [
          'dev_requirement' => '^1@dev',
          'dev_requirement_2' => '^1',
        ],
      ],
      'contrib_needs_other_version' => [
        0,
        ['dev_requirement' => '2.0.0'],
        ['dev_requirement' => '1.0.*'],
      ],
    ];
  }

  /**
   * @covers ::buildRequirements
   * @dataProvider provideBuildRequirements
   */
  public function testBuildRequirements($expected, $installed, $dev_requirements) {
    $mock_root = $this->getMockBuilder(ProjectRootInterface::class)
      ->setMethods(['getInstalledPackages', 'getDevRequirements'])
      ->getMockForAbstractClass();
    $mock_root->expects($this->once())
      ->method('getInstalledPackages')
      ->willReturn($installed);
    $mock_root->expects($this->once())
      ->method('getDevRequirements')
      ->willReturn($dev_requirements);

    $finder = new DevDependencyFinder(
      $mock_root,
      $this->getMockForAbstractClass(TranslationInterface::class)
    );
    $requirements = $finder->buildRequirements();

    $this->assertArrayHasKey('composer_dev_dependencies', $requirements);
    foreach (['title', 'description', 'severity'] as $property) {
      $this->assertArrayHasKey($property, $requirements['composer_dev_dependencies']);
    }
    $this->assertEquals($expected, $requirements['composer_dev_dependencies']['severity']);
  }

}
