<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Unit;

use Drupal\media\OEmbed\Endpoint;
use Drupal\media\OEmbed\Provider;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\media\OEmbed\Endpoint.
 */
#[CoversClass(Endpoint::class)]
#[Group('media')]
class EndpointTest extends UnitTestCase {

  /**
   * Tests match url.
   */
  public function testMatchUrl(): void {
    $endpoint = new Endpoint(
      'https://www.youtube.com/oembed',
      $this->createStub(Provider::class),
      ['https://*.youtube.com/playlist?list=*']
    );
    $this->assertTrue($endpoint->matchUrl('https://www.youtube.com/playlist?list=aBc-EzAs123'));
  }

  /**
   * Tests case sensitive match.
   *
   * @legacy-covers ::matchUrl
   */
  public function testCaseSensitiveMatch(): void {
    $endpoint = new Endpoint(
      'https://www.example.com/oembed',
      $this->createStub(Provider::class),
      ['https://*.example.com/Video/*'],
    );
    $this->assertTrue($endpoint->matchUrl('https://foo.example.com/Video/bar'));
  }

  /**
   * @covers ::matchUrl
   *
   * @dataProvider providerMatchUrlSpecialCharacters
   */
  public function testMatchUrlWithSpecialCharacters(string $endpointScheme, string $matchUrl, bool $match): void {
    $endpoint = new Endpoint(
      'https://www.example.com/oembed',
      $this->createMock('\Drupal\media\OEmbed\Provider'),
      [$endpointScheme],
    );
    $this->assertSame($match, $endpoint->matchUrl($matchUrl));
  }

  /**
   * Data provider for testMatchUrlWithSpecialCharacters.
   */
  public static function providerMatchUrlSpecialCharacters(): array {
    return [
      // Some providers use regex that does not follow spec, we don't support it.
      ['https://(m|www).example.com/video/*', 'https://m.example.com/video/bar', FALSE],
      ['http://*.deviantart.com/*#/d*', 'http://foo.deviantart.com/bar#/dbaz', TRUE],
    ];
  }

}
