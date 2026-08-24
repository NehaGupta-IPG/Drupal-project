<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Test;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests Suite Base.
 */
#[Group('Test')]
class TestSuiteBaseTest extends TestCase {

  /**
   * Tests the assumption that local time is in 'Australia/Sydney'.
   */
  public function testLocalTimeZone(): void {
    // The 'Australia/Sydney' time zone is set in core/tests/bootstrap.php.
    $this->assertEquals('Australia/Sydney', date_default_timezone_get());
  }

  /**
   * Tests that extension discovery does not recurse into irrelevant directories.
   */
  public function testExtensionDirectoryDiscoverySkipsIrrelevantDirectories(): void {
    $root = vfsStream::setup('root', NULL, [
      'modules' => [
        'example' => [
          'example.info.yml' => '',
          'node_modules' => [
            'not_an_extension' => [
              'not_an_extension.info.yml' => '',
            ],
          ],
        ],
      ],
      '.git' => [
        'not_an_extension' => [
          'not_an_extension.info.yml' => '',
        ],
      ],
    ]);

    $extensions = \drupal_phpunit_find_extension_directories($root->url());

    $this->assertSame(['example'], array_keys($extensions));
  }

}
