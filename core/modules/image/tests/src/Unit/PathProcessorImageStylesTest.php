<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Unit;

use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\image\PathProcessor\PathProcessorImageStyles;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests inbound image style path processing.
 */
#[CoversClass(PathProcessorImageStyles::class)]
#[CoversMethod(PathProcessorImageStyles::class, 'processInbound')]
#[Group('image')]
class PathProcessorImageStylesTest extends UnitTestCase {

  /**
   * Tests inbound processing of image style paths.
   */
  #[DataProvider('providerProcessInbound')]
  public function testProcessInbound(string $directory_path, string $path, string $expected, array $expected_query = []): void {
    $request = new Request();
    $processor = $this->createProcessor($directory_path);
    $this->assertSame($expected, $processor->processInbound($path, $request));
    $this->assertSame($expected_query, $request->query->all());
  }

  /**
   * Inbound paths and expected results.
   */
  public static function providerProcessInbound(): array {
    return [
      'empty public directory path' => [
        '',
        '/styles/thumbnail/public/example.jpg',
        '/styles/thumbnail/public',
        ['file' => 'example.jpg'],
      ],
      'empty public directory path with nested file' => [
        '',
        '/styles/wide/public/subdir/nested.jpg',
        '/styles/wide/public',
        ['file' => 'subdir/nested.jpg'],
      ],
      'default public directory path' => [
        'sites/default/files',
        '/sites/default/files/styles/thumbnail/public/example.jpg',
        '/sites/default/files/styles/thumbnail/public',
        ['file' => 'example.jpg'],
      ],
      'private image style with empty public directory path' => [
        '',
        '/system/files/styles/thumbnail/private/example.jpg',
        '/system/files/styles/thumbnail/private',
        ['file' => 'example.jpg'],
      ],
      'unrelated path is unchanged' => [
        '',
        '/node/1',
        '/node/1',
      ],
    ];
  }

  /**
   * Creates a path processor with a stub public stream wrapper.
   *
   * @param string $directory_path
   *   Directory path returned by the public stream wrapper.
   *
   * @return \Drupal\image\PathProcessor\PathProcessorImageStyles
   *   The path processor under test.
   */
  protected function createProcessor(string $directory_path): PathProcessorImageStyles {
    $stream_wrapper = $this->createStub(LocalStream::class);
    $stream_wrapper->method('getDirectoryPath')->willReturn($directory_path);
    $stream_wrapper_manager = $this->createStub(StreamWrapperManagerInterface::class);
    $stream_wrapper_manager->method('getViaScheme')
      ->willReturn($stream_wrapper);
    return new PathProcessorImageStyles($stream_wrapper_manager);
  }

}
