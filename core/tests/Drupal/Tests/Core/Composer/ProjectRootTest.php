<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Composer;

use Drupal\Core\Composer\ProjectRoot;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * @internal
 */
#[Group('Composer')]
#[CoversClass(ProjectRoot::class)]
#[CoversMethod(ProjectRoot::class, 'isDevInstall')]
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
  protected function getMockProjectRoot(string $root_path): ProjectRoot {
    $root = $this->getMockBuilder(ProjectRoot::class)
      ->onlyMethods(['getProjectVendorDirectory'])
      ->setConstructorArgs([vfsStream::url($root_path)])
      ->getMock();
    $root->expects($this->once())
      ->method('getProjectVendorDirectory')
      ->willReturn(vfsStream::url($root_path) . '/vendor');
    return $root;
  }

  public static function provideInstalledJson(): array {
    return [
      [
        TRUE,
        ['vendor' => ['composer' => ['installed.json' => '{"dev": true}']]],
      ],
      [
        FALSE,
        ['vendor' => ['composer' => ['installed.json' => '{"dev": false}']]],
      ],
    ];
  }

  #[DataProvider('provideInstalledJson')]
  public function testIsDevInstall($expected, $filesystem): void {
    vfsStream::setup('root_test', NULL, $filesystem);
    $root = $this->getMockProjectRoot('root_test');
    $this->assertEquals($expected, $root->isDevInstall());
  }

}
