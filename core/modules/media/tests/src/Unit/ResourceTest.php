<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Unit;

use Drupal\media\OEmbed\Provider;
use Drupal\media\OEmbed\Resource;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\media\OEmbed\Resource
 * @group media
 */
class ResourceTest extends UnitTestCase {

  /**
   * Test cases for ::testSetDimensions.
   */
  public static function setDimensionsTestCases() {
    return [
      'Standard rich dimensions' => [
        'rich',
        5,
        10,
      ],
      'Negative width and height' => [
        'rich',
        -5,
        -10,
        'The dimensions must be NULL or numbers greater than zero.',
      ],
      'Zero width' => [
        'rich',
        0,
        5,
        'The dimensions must be NULL or numbers greater than zero.',
      ],
      'NULL width' => [
        'rich',
        NULL,
        10,
      ],
      'NULL height' => [
        'rich',
        NULL,
        10,
      ],
      'NULL width and height' => [
        'rich',
        NULL,
        NULL,
      ],
      'Cast numeric dimensions' => [
        'rich',
        "1",
        "45",
        NULL,
        1,
        45,
      ],
      'Cast invalid zero value' => [
        'rich',
        "0",
        10,
        'The dimensions must be NULL or numbers greater than zero.',
      ],
      'Cast negative value' => [
        'rich',
        "-10",
        10,
        'The dimensions must be NULL or numbers greater than zero.',
      ],
    ];
  }

  /**
   * @covers ::setDimensions
   * @dataProvider setDimensionsTestCases
   */
  public function testSetDimensions($factory, $width, $height, $exception = NULL, $expected_width = NULL, $expected_height = NULL): void {
    if ($exception) {
      $this->expectException(\InvalidArgumentException::class);
      $this->expectExceptionMessage($exception);
    }
    $resource = Resource::$factory('foo', $width, $height);
    $this->assertSame($expected_width ?: $width, $resource->getWidth());
    $this->assertSame($expected_height ?: $height, $resource->getHeight());
  }

  /**
   * Tests thumbnail dimension conditional logic.
   *
   * @covers ::setThumbnailDimensions
   */
  public function testThumbnailDimensionsConditional(): void {
    // Mock provider
    $provider = $this->createMock(Provider::class);

    // Case 1: Both dimensions provided
    $resource1 = Resource::photo(
      'https://example.com/photo1.jpg',
      800,
      600,
      $provider,
      'Photo with Thumbnail',
      '',
      '',
      3600,
      'https://example.com/thumb1.jpg',
      150,
      150
    );
    $this->assertSame(150, $resource1->getThumbnailWidth());
    $this->assertSame(150, $resource1->getThumbnailHeight());

    // Case 2: One dimension missing
    $resource2 = Resource::photo(
      'https://example.com/photo2.jpg',
      800,
      600,
      $provider,
      'Photo without Thumbnail',
      '',
      '',
      3600,
      'https://example.com/thumb2.jpg',
      NULL,
      150
    );
    $this->assertNull($resource2->getThumbnailWidth());
    $this->assertNull($resource2->getThumbnailHeight());

    // Case 3: Zero dimensions
    $resource3 = Resource::photo(
      'https://example.com/photo3.jpg',
      800,
      600,
      $provider,
      'Photo with Zero Dimensions',
      '',
      '',
      3600,
      'https://example.com/thumb3.jpg',
      0,
      0
    );
    $this->assertNull($resource3->getThumbnailWidth());
    $this->assertNull($resource3->getThumbnailHeight());

    // Case 4: Both dimensions missing
    $resource2 = Resource::photo(
      'https://example.com/photo2.jpg',
      800,
      600,
      $provider,
      'Photo without Thumbnail',
      '',
      '',
      3600,
      'https://example.com/thumb2.jpg'
    );
    $this->assertNull($resource2->getThumbnailWidth());
    $this->assertNull($resource2->getThumbnailHeight());
  }

}
