<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Composer;

use Drupal\Core\Composer\ProjectRoot;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;

/**
 * @coversDefaultClass \Drupal\Core\Composer\ProjectRoot
 * @group Composer
 *
 * @internal
 */
class ProjectRootTest extends UnitTestCase {

  /**
   * Generate a mock ProjectRoot object for testing.
   *
   * Since we can't unit test getProjectVendorDirectory(), we have to mock it so
   * that it returns a reasonable value. This allows us to test the rest of the
   * class.
   *
   * @param string $root_path
   *   The root directory of the vfsStream mock.
   */
  protected function getMockProjectRoot($root_path): ProjectRoot {
    $root = $this->getMockBuilder(ProjectRoot::class)
      ->onlyMethods(['getProjectVendorDirectory'])
      ->setConstructorArgs([vfsStream::url($root_path)])
      ->getMock();
    $root->expects($this->once())
      ->method('getProjectVendorDirectory')
      ->willReturn(vfsStream::url($root_path) . '/vendor');
    return $root;
  }

  public static function provideEmptyInstalledJson(): array {
    return [
      ['filesystem' => ['vendor' => ['composer' => []]]],
      ['filesystem' => ['vendor' => ['composer' => ['installed.json' => ' ']]]],
      ['filesystem' => ['vendor' => ['composer' => ['installed.json' => '[]']]]],
      ['filesystem' => ['vendor' => ['composer' => ['installed.json' => '[{"key": "value"}]']]]],
    ];
  }

  /**
   * Verify behavior for missing or bad installed.json.
   *
   * @covers ::getInstalledPackages
   * @dataProvider provideEmptyInstalledJson
   */
  public function testGetInstalledPackagesEmpty($filesystem): void {
    vfsStream::setup('root_test', NULL, $filesystem);
    $root = $this->getMockProjectRoot('root_test');
    $this->assertSame([], $root->getInstalledPackages());
  }

  public static function provideInstalledJson(): array {
    return [
      [
        ['package_name' => 'package_version'],
        ['vendor' => ['composer' => ['installed.json' => '[{"name": "package_name", "version_normalized": "package_version"}]']]],
      ],
    ];
  }

  /**
   * @covers ::getInstalledPackages
   * @dataProvider provideInstalledJson
   */
  public function testGetInstalledPackages($expected, $filesystem): void {
    vfsStream::setup('root_test', NULL, $filesystem);
    $root = $this->getMockProjectRoot('root_test');
    $this->assertEquals($expected, $root->getInstalledPackages());
  }

}
