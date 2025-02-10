<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Theme\Icon;

use Drupal\Core\Theme\Icon\IconFinder;
use Drupal\Core\Theme\Icon\IconFinderInterface;
use Drupal\KernelTests\KernelTestBase;
use Psr\Log\LoggerInterface;

/**
 * Test icon sources path generated urls.
 *
 * @group icon
 *
 * @coversDefaultClass \Drupal\Core\Theme\Icon\IconFinder
 */
class IconFinderKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
  ];

  /**
   * The IconFinder instance.
   *
   * @var \Drupal\Core\Theme\Icon\IconFinderInterface
   */
  private IconFinderInterface $iconFinder;

  /**
   * The App root instance.
   *
   * @var string
   */
  private string $appRoot;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $fileUrlGenerator = $this->container->get('file_url_generator');
    $this->appRoot = $this->container->getParameter('app.root');

    $this->iconFinder = new IconFinder(
      $fileUrlGenerator,
      $this->createMock(LoggerInterface::class),
      $this->appRoot,
    );
  }

  /**
   * Test the IconFinder::_construct method.
   */
  public function testConstructor(): void {
    $this->assertInstanceOf(IconFinder::class, $this->iconFinder);
  }

  /**
   * Data provider for ::testGetFilesFromSourcesPath().
   *
   * @return \Generator
   *   The test cases, to minimize test data, result expected is an array with:
   *   - icon_id: the expected id
   *   - path: expected path found relative to TEST_ICONS_PATH
   *   - group: The group name if any
   */
  public static function providerGetFilesFromSourcesPath(): iterable {
    yield 'path relative to app root' => [
      [
        '/core/misc/druplicon.png',
        '/core/misc/feed.svg',
      ],
      [
        'druplicon' => [
          'icon_id' => 'druplicon',
          'source' => '/core/misc/druplicon.png',
          'absolute_path' => '{appRoot}/core/misc/druplicon.png',
          'group' => NULL,
        ],
        'feed' => [
          'icon_id' => 'feed',
          'source' => '/core/misc/feed.svg',
          'absolute_path' => '{appRoot}/core/misc/feed.svg',
          'group' => NULL,
        ],
      ],
    ];

    yield 'path relative to icon definition' => [
      [
        'icons/flat/foo.png',
        'icons/flat/bar.svg',
      ],
      [
        'foo' => [
          'icon_id' => 'foo',
          'source' => '/core/modules/system/tests/modules/icon_test/icons/flat/foo.png',
          'absolute_path' => '{appRoot}/core/modules/system/tests/modules/icon_test/icons/flat/foo.png',
          'group' => NULL,
        ],
        'bar' => [
          'icon_id' => 'bar',
          'source' => '/core/modules/system/tests/modules/icon_test/icons/flat/bar.svg',
          'absolute_path' => '{appRoot}/core/modules/system/tests/modules/icon_test/icons/flat/bar.svg',
          'group' => NULL,
        ],
      ],
      'core/modules/system/tests/modules/icon_test',
    ];
  }

  /**
   * Test the IconFinder::getFilesFromSources method with paths.
   *
   * @param array<string> $sources
   *   The list of remote.
   * @param array<string, string> $expected
   *   The expected result.
   * @param string $relative_path
   *   The relative path to simulate an icon in the module/theme definition.
   *
   * @dataProvider providerGetFilesFromSourcesPath
   */
  public function testGetFilesFromSourcesPath(array $sources, array $expected, string $relativePath = ''): void {
    $result = $this->iconFinder->getFilesFromSources(
      $sources,
      $relativePath,
    );

    foreach ($expected as $key => $value) {
      $expected[$key]['absolute_path'] = str_replace('{appRoot}', $this->appRoot, $expected[$key]['absolute_path']);
    }

    $this->assertEquals($result, $expected);
  }

}
