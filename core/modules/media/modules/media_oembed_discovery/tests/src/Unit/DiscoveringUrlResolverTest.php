<?php

declare(strict_types=1);

namespace Drupal\Tests\media_oembed_discovery\Unit;

use Drupal\Core\Cache\NullBackend;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\media\OEmbed\ProviderRepositoryInterface;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Drupal\media_oembed_discovery\OEmbed\DiscoveringUrlResolver;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the discovering oEmbed URL resolver.
 */
#[CoversClass(DiscoveringUrlResolver::class)]
#[Group('media_oembed_discovery')]
class DiscoveringUrlResolverTest extends UnitTestCase {

  /**
   * Creates a DiscoveringUrlResolver with exposed protected methods.
   *
   * @param \GuzzleHttp\Client $client
   *   The HTTP client.
   *
   * @return \Drupal\media_oembed_discovery\OEmbed\DiscoveringUrlResolver
   *   A DiscoveringUrlResolver instance with a public discoverResourceUrl
   *   method.
   */
  protected function createTestableUrlResolver(Client $client): DiscoveringUrlResolver {
    return new class (
      $this->createStub(ProviderRepositoryInterface::class),
      $this->createStub(ResourceFetcherInterface::class),
      $client,
      $this->createStub(ModuleHandlerInterface::class),
      new NullBackend('default'),
    ) extends DiscoveringUrlResolver {

      /**
       * {@inheritdoc}
       */
      public function discoverResourceUrl(string $url): string|false {
        return parent::discoverResourceUrl($url);
      }

    };
  }

  /**
   * Tests that discoverResourceUrl parses HTML responses.
   */
  public function testDiscoverResourceUrlParsesHtml(): void {
    $html_with_oembed = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <link rel="alternate" href="https://example.com/oembed?url=test" type="application/json+oembed">
</head>
<body></body>
</html>
HTML;

    $response = new Response(200, ['Content-Type' => 'text/html'], $html_with_oembed);
    $mock_handler = new MockHandler([$response]);
    $client = new Client(['handler' => HandlerStack::create($mock_handler)]);

    $url_resolver = $this->createTestableUrlResolver($client);
    $result = $url_resolver->discoverResourceUrl('https://example.com/some-page');

    $this->assertSame('https://example.com/oembed?url=test', $result);
  }

  /**
   * Tests that discoverResourceUrl skips non-HTML responses.
   */
  public function testDiscoverResourceUrlSkipsNonHtml(): void {
    $response = new Response(200, ['Content-Type' => 'application/json'], '');
    $mock_handler = new MockHandler([$response]);
    $client = new Client(['handler' => HandlerStack::create($mock_handler)]);

    $url_resolver = $this->createTestableUrlResolver($client);
    $result = $url_resolver->discoverResourceUrl('https://example.com/some-page');

    $this->assertFalse($result);
  }

}
