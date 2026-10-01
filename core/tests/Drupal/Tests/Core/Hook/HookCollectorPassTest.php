<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Hook;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Hook\HookCollectorBase;
use Drupal\Core\Hook\HookCollectorPass;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests Drupal\Core\Hook\HookCollectorPass.
 */
#[CoversClass(HookCollectorPass::class)]
#[Group('Hook')]
class HookCollectorPassTest extends UnitTestCase {

  public function testCollectsOnlyRootProceduralAndHookDirectoryFiles(): void {
    vfsStream::setup('drupal_root');
    vfsStream::create([
      'test_module' => [
        'ignored.php' => '',
        'src' => [
          'Hook' => [
            'TestHooks.php' => '',
            'Subdirectory' => ['NestedHooks.php' => ''],
          ],
          'Other' => ['IgnoredHooks.php' => ''],
        ],
        'ignored_directory' => ['ignored.module' => ''],
      ],
    ]);

    $collector = new HookCollectorPass();
    $get_files = \Closure::bind(
      static function (HookCollectorPass $collector, string $directory, string $module): array {
        return iterator_to_array($collector->getHookFileIterator($directory));
      },
      NULL,
      HookCollectorBase::class,
    );
    $files = $get_files($collector, 'vfs://drupal_root/test_module', 'test_module');
    $paths = array_map(static fn (\SplFileInfo $file): string => $file->getPathname(), $files);
    sort($paths);

    $this->assertSame([
      'vfs://drupal_root/test_module/src/Hook/Subdirectory/NestedHooks.php',
      'vfs://drupal_root/test_module/src/Hook/TestHooks.php',
    ], $paths);
  }

}
