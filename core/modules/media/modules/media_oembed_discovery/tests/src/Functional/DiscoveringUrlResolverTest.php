<?php

declare(strict_types=1);

namespace Drupal\Tests\media_oembed_discovery\Functional;

use Drupal\media_oembed_discovery\OEmbed\DiscoveringUrlResolver;
use Drupal\Tests\media\Functional\MediaFunctionalTestBase;
use Drupal\Tests\media\Traits\OEmbedTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// cspell:ignore dailymotion

/**
 * Tests oEmbed URL resolution falling back to discovery.
 */
#[CoversClass(DiscoveringUrlResolver::class)]
#[Group('media')]
#[Group('media_oembed_discovery')]
#[RunTestsInSeparateProcesses]
class DiscoveringUrlResolverTest extends MediaFunctionalTestBase {

  use OEmbedTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['media_oembed_discovery'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->lockHttpClientToFixtures();
    $this->useFixtureProviders();
  }

  /**
   * Data provider for testUrlDiscovery().
   *
   * @return array
   *   An array of test data.
   */
  public static function providerUrlDiscovery(): array {
    return [
      'JSON resource' => [
        'video_vimeo.html',
        'https://vimeo.com/api/oembed.json?url=video_vimeo.html',
      ],
      'XML resource' => [
        'video_dailymotion.html',
        'https://www.dailymotion.com/services/oembed?url=video_dailymotion.html',
      ],
    ];
  }

  /**
   * Tests URL resolution when the URL is discovered by scanning the asset.
   *
   * @param string $url
   *   The asset URL to resolve.
   * @param string $resource_url
   *   The expected oEmbed resource URL of the asset.
   */
  #[DataProvider('providerUrlDiscovery')]
  public function testUrlDiscovery(string $url, string $resource_url): void {
    $this->assertSame(
      $resource_url,
      $this->container->get('media.oembed.url_resolver')->getResourceUrl($url)
    );
  }

  /**
   * Tests that endpoint pattern matching still works with discovery enabled.
   */
  public function testEndpointMatchingStillWorks(): void {
    $this->assertSame(
      'https://vimeo.com/api/oembed.json?url=https%3A//vimeo.com/14782834',
      $this->container->get('media.oembed.url_resolver')
        ->getResourceUrl('https://vimeo.com/14782834')
    );
  }

}
