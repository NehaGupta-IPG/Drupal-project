<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\PathProcessor;

use Drupal\Core\PathProcessor\PathProcessorDecode;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests URL decoding path processing.
 */
#[CoversClass(PathProcessorDecode::class)]
#[Group('PathProcessor')]
class PathProcessorDecodeTest extends UnitTestCase {

  /**
   * Tests inbound path decoding with various encoded paths.
   */
  #[DataProvider('providerProcessInbound')]
  public function testProcessInbound(string $path, string $expected): void {
    $processor = new PathProcessorDecode();
    $request = Request::create($path);
    $this->assertEquals($expected, $processor->processInbound($path, $request));
  }

  /**
   * Provides test paths and expected decoded results.
   */
  public static function providerProcessInbound(): array {
    return [
      'plus sign preserved' => [
        '/books/horror+thriller',
        '/books/horror+thriller',
      ],
      'encoded space' => [
        '/books/horror%20thriller',
        '/books/horror thriller',
      ],
      'encoded plus sign' => [
        '/books/horror%2Bthriller',
        '/books/horror+thriller',
      ],
      'encoded parentheses' => [
        '/category%28test%29',
        '/category(test)',
      ],
      'encoded hash' => [
        '/category%23test',
        '/category#test',
      ],
      'simple path unchanged' => [
        '/node/1',
        '/node/1',
      ],
    ];
  }

}
