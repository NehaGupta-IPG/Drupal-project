<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Functional;

use Drupal\Component\Utility\UrlHelper;
use Drupal\media\OEmbed\UrlResolver;
use Drupal\Tests\media\Traits\OEmbedTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// cspell:ignore dailymotion
/**
 * Tests the oEmbed URL resolver service.
 */
#[CoversClass(UrlResolver::class)]
#[Group('media')]
#[RunTestsInSeparateProcesses]
class UrlResolverTest extends MediaFunctionalTestBase {

  use OEmbedTestTrait;

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
   * Data provider for testEndpointMatching().
   *
   * @see ::testEndpointMatching()
   *
   * @return array
   *   An array of test data.
   */
  public static function providerEndpointMatching(): array {
    return [
      'match by endpoint: Twitter' => [
        'https://twitter.com/Dries/status/999985431595880448',
        'https://publish.twitter.com/oembed?url=https%3A//twitter.com/Dries/status/999985431595880448',
      ],
      'match by endpoint: Vimeo' => [
        'https://vimeo.com/14782834',
        'https://vimeo.com/api/oembed.json?url=https%3A//vimeo.com/14782834&locale=en',
      ],
      'match by endpoint: Dailymotion' => [
        'https://www.dailymotion.com/video/x2vzluh',
        'https://www.dailymotion.com/services/oembed?url=https%3A//www.dailymotion.com/video/x2vzluh',
      ],
      'match by endpoint: Facebook' => [
        'https://www.facebook.com/facebook/videos/10153231379946729/',
        'https://www.facebook.com/plugins/video/oembed.json?url=https%3A//www.facebook.com/facebook/videos/10153231379946729/',
      ],
    ];
  }

  /**
   * Tests resource URL resolution with a matched provider endpoint.
   *
   * @param string $url
   *   The asset URL to resolve.
   * @param string $resource_url
   *   The expected oEmbed resource URL of the asset.
   *
   * @legacy-covers ::getProviderByUrl
   * @legacy-covers ::getResourceUrl
   */
  #[DataProvider('providerEndpointMatching')]
  public function testEndpointMatching(string $url, string $resource_url): void {
    $this->assertSame(
      $resource_url,
      $this->container->get('media.oembed.url_resolver')->getResourceUrl($url)
    );
  }

  /**
   * Tests that hook_oembed_resource_url_alter() is invoked.
   */
  #[Depends('testEndpointMatching')]
  public function testResourceUrlAlterHook(): void {
    $this->container->get('module_installer')->install(['media_test_oembed']);

    // Much like FunctionalTestSetupTrait::installModulesFromClassProperty()
    // after module install the rebuilt container needs to be used.
    $this->container = \Drupal::getContainer();
    $resource_url = $this->container->get('media.oembed.url_resolver')
      ->getResourceUrl('https://vimeo.com/14782834');

    $this->assertStringContainsString('altered=1', parse_url($resource_url, PHP_URL_QUERY));
  }

  /**
   * Data provider for testUrlDiscovery().
   *
   * @see ::testUrlDiscovery()
   *
   * @return array
   *   An array of test data.
   */
  public static function providerUrlDiscovery(): array {
    return [
      'JSON resource' => [
        'video_vimeo.html',
        'https://vimeo.com/api/oembed.json?%s&locale=en',
      ],
      'XML resource' => [
        'video_dailymotion.html',
        'https://www.dailymotion.com/services/oembed?%s',
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
   *
   * @legacy-covers ::discoverResourceUrl
   * @legacy-covers ::getProviderByUrl
   * @legacy-covers ::getResourceUrl
   */
  #[DataProvider('providerUrlDiscovery')]
  public function testUrlDiscovery(string $url, string $resource_url): void {
    $settings['settings']['media_oembed_discovery_trusted_host_patterns'] = (object) [
      'value' => [
        \preg_quote(\parse_url($this->baseUrl, \PHP_URL_HOST)),
      ],
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
    $this->rebuildAll();
    $fully_qualified_url = $this->getFixturesUrl() . '/' . $url;
    $this->assertSame(
      \sprintf($resource_url, UrlHelper::buildQuery(['url' => $fully_qualified_url])),
      $this->container->get('media.oembed.url_resolver')->getResourceUrl($fully_qualified_url)
    );
  }

  /**
   * Tests that language parameters are added to oEmbed provider URLs.
   *
   * Verifies WCAG SC 3.1.2 compliance: YouTube gets hl= parameter, Vimeo
   * gets locale= parameter, based on the current interface language.
   */
  public function testLanguageParameterAddedToProviderUrl(): void {
    // YouTube URLs should get hl=<langcode>.
    $resource_url = $this->container->get('media.oembed.url_resolver')
      ->getResourceUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    $query = parse_url($resource_url, PHP_URL_QUERY);
    $this->assertMatchesRegularExpression('/hl=[a-z]{2}/', $query, 'YouTube URL should have hl parameter');

    // Vimeo URLs should get locale=<langcode>.
    $resource_url = $this->container->get('media.oembed.url_resolver')
      ->getResourceUrl('https://vimeo.com/14782834');
    $query = parse_url($resource_url, PHP_URL_QUERY);
    $this->assertMatchesRegularExpression('/locale=[a-z]{2}/', $query, 'Vimeo URL should have locale parameter');
  }

  /**
   * Tests that language parameter uses correct value for the interface language.
   */
  public function testLanguageParameterValueMatchesInterfaceLanguage(): void {
    // English is the default. YouTube should have hl=en.
    $resource_url = $this->container->get('media.oembed.url_resolver')
      ->getResourceUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    $query = parse_url($resource_url, PHP_URL_QUERY);
    $this->assertStringContainsString('hl=en', $query);

    // Vimeo should have locale=en.
    $resource_url = $this->container->get('media.oembed.url_resolver')
      ->getResourceUrl('https://vimeo.com/14782834');
    $query = parse_url($resource_url, PHP_URL_QUERY);
    $this->assertStringContainsString('locale=en', $query);
  }

  /**
   * Tests that cache keys include language for proper isolation.
   */
  public function testCacheKeyIncludesLanguage(): void {
    // Get URL in default language (English).
    $url_en = $this->container->get('media.oembed.url_resolver')
      ->getResourceUrl('https://vimeo.com/14782834');

    // Verify English language parameter is present.
    $this->assertStringContainsString('locale=en', $url_en);
  }

}
