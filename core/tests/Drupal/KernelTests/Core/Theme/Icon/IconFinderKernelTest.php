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
 * Test using the service fileUrlGenerator for a real generateString().
 * It rely on base_path() that we override here to allow tests with custom
 * values.
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

    /** @var \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator */
    $fileUrlGenerator = $this->container->get('file_url_generator');
    $this->appRoot = $this->container->getParameter('app.root');

    $this->iconFinder = new IconFinder(
      $fileUrlGenerator,
      $this->createMock(LoggerInterface::class),
      $this->appRoot,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    $GLOBALS['base_path'] = '/';
    parent::tearDown();
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
   * @return array
   *   The test cases, to minimize test data, result expected is an array with:
   *   - icon_id: the expected id
   *   - source: the expected source value, without left base path,
   *     processed through generateString().
   */
  public static function providerGetFilesFromSourcesPath(): array {
    return [
      [
        [
          // Use real icons for better test, but `/core` are not managed by
          // icon_test so they could change and make this test fail.
          '/core/misc/druplicon.png',
          'icons/flat/foo.png',
        ],
        [
          'druplicon' => 'core/misc/druplicon.png',
          'foo' => 'core/modules/system/tests/modules/icon_test/icons/flat/foo.png',
        ],
        'core/modules/system/tests/modules/icon_test',
      ],
    ];
  }

  /**
   * Test the IconFinder::getFilesFromSources method with paths.
   *
   * @param array<string> $sources
   *   The list of remote.
   * @param array<string, string> $expected
   *   The expected result as icon_id => source.
   * @param string $relativePath
   *   The relative path to simulate an icon in the module/theme definition.
   *
   * @dataProvider providerGetFilesFromSourcesPath
   */
  public function testGetFilesFromSourcesPath(array $sources, array $expected, string $relativePath): void {
    $base_path_test = ['/', '/foo/', '/foo/bar/'];

    foreach ($base_path_test as $base_path) {
      // @todo Remove or adapt as part of https://www.drupal.org/node/2529170.
      $GLOBALS['base_path'] = $base_path;

      $result = $this->iconFinder->getFilesFromSources(
        $sources,
        $relativePath,
      );

      // Prepare result array matching processFoundFiles() to minimize test data.
      $expected_result = [];
      foreach ($expected as $key => $expected_value) {
        $expected_result[$key] = [
          'icon_id' => $key,
          'source' => $base_path . $expected_value,
          'absolute_path' => DRUPAL_ROOT . '/' . $expected_value,
          'group' => NULL,
        ];
      }

      $this->assertEquals($result, $expected_result);
    }
  }

}
