<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Kernel;

use Drupal\Component\Datetime\Time;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media\Entity\Media;
use Drupal\media\OEmbed\Provider;
use Drupal\media\OEmbed\ProviderRepositoryInterface;
use Drupal\media\OEmbed\ResourceFetcher;
use Drupal\media\OEmbed\UrlResolverInterface;
use Drupal\media\Plugin\media\Source\OEmbed;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests thumbnail selection through the core oEmbed media source.
 */
#[CoversClass(OEmbed::class)]
#[Group('media')]
#[RunTestsInSeparateProcesses]
class OEmbedThumbnailTest extends MediaKernelTestBase {

  /**
   * Tests preferred image download and dimensions.
   */
  public function testPreferredThumbnailIsSavedAndMetadataIsCached(): void {
    $original = 'https://i.ytimg.com/vi/ABCDEFGHIJK/hqdefault.jpg';
    $candidate = 'https://i.ytimg.com/vi/ABCDEFGHIJK/maxresdefault.jpg';
    $image = $this->imageBytes(1280, 720);
    [$media, $source, $getHistory] = $this->createMediaWithServices([
      $this->oembedResponse($original, 480, 360, 640, 360),
      new Response(200, ['Content-Type' => 'image/png'], $image),
    ]);

    $uri = $source->getMetadata($media, 'thumbnail_uri');

    $this->assertNotNull($uri);
    $this->assertStringContainsString('public://oembed-thumbnail-test/2024-01/', $uri);
    $this->assertSame(1280, $source->getMetadata($media, 'thumbnail_width'));
    $this->assertSame(720, $source->getMetadata($media, 'thumbnail_height'));
    $this->assertSame($uri, $source->getMetadata($media, 'thumbnail_uri'));
    $this->assertCount(2, $getHistory());
    $this->assertSame('https://example.com/oembed?url=resource', (string) $getHistory()[0]['request']->getUri());
    $this->assertSame($candidate, (string) $getHistory()[1]['request']->getUri());
    $this->assertSame(hash('sha256', $image), hash_file('sha256', $uri));
    $this->assertSame([1280, 720], array_slice(getimagesize($uri), 0, 2));
  }

  /**
   * Tests that failed preferred candidates fall back to the original image.
   */
  public function testPreferredFailuresFallBackToOriginal(): void {
    $original = 'https://i.ytimg.com/vi/ABCDEFGHIJK/hqdefault.jpg';
    $image = $this->imageBytes(480, 360);
    [$media, $source, $getHistory] = $this->createMediaWithServices([
      $this->oembedResponse($original, 480, 360, 640, 360),
      new Response(404),
      new Response(403),
      new Response(200, ['Content-Type' => 'image/png'], $image),
    ]);

    $uri = $source->getMetadata($media, 'thumbnail_uri');

    $this->assertSame(480, $source->getMetadata($media, 'thumbnail_width'));
    $this->assertSame(360, $source->getMetadata($media, 'thumbnail_height'));
    $this->assertCount(4, $getHistory());
    $this->assertSame($original, (string) $getHistory()[3]['request']->getUri());
    $this->assertSame(hash('sha256', $image), hash_file('sha256', $uri));
  }

  /**
   * Tests invalid high-resolution candidates falling back to the original.
   */
  public function testInvalidPreferredCandidatesFallBackToOriginal(): void {
    $original = 'https://i.ytimg.com/vi/ABCDEFGHIJK/hqdefault.jpg';
    $image = $this->imageBytes(480, 360);
    [$media, $source, $getHistory] = $this->createMediaWithServices([
      $this->oembedResponse($original, 480, 360, 640, 360),
      new Response(200, ['Content-Type' => 'text/html'], '<html></html>'),
      new Response(200, ['Content-Type' => 'image/png'], $this->imageBytes(120, 90)),
      new Response(200, ['Content-Type' => 'image/png'], $image),
    ]);

    $uri = $source->getMetadata($media, 'thumbnail_uri');

    $this->assertSame(480, $source->getMetadata($media, 'thumbnail_width'));
    $this->assertSame(360, $source->getMetadata($media, 'thumbnail_height'));
    $this->assertCount(4, $getHistory());
    $this->assertSame($original, (string) $getHistory()[3]['request']->getUri());
    $this->assertSame(hash('sha256', $image), hash_file('sha256', $uri));
    $this->assertSame([480, 360], array_slice(getimagesize($uri), 0, 2));
  }

  /**
   * Tests that a connection failure advances to the next preferred candidate.
   */
  public function testConnectionFailureFallsBackToNextPreferredCandidate(): void {
    $original = 'https://i.ytimg.com/vi/ABCDEFGHIJK/hqdefault.jpg';
    $candidate = 'https://i.ytimg.com/vi/ABCDEFGHIJK/sddefault.jpg';
    $image = $this->imageBytes(640, 480);
    [$media, $source, $getHistory] = $this->createMediaWithServices([
      $this->oembedResponse($original, 480, 360, 640, 360),
      new ConnectException('connection failed', new Request('GET', 'https://i.ytimg.com/vi/ABCDEFGHIJK/maxresdefault.jpg')),
      new Response(200, ['Content-Type' => 'image/png'], $image),
    ]);

    $uri = $source->getMetadata($media, 'thumbnail_uri');

    $this->assertSame(640, $source->getMetadata($media, 'thumbnail_width'));
    $this->assertSame(480, $source->getMetadata($media, 'thumbnail_height'));
    $this->assertCount(3, $getHistory());
    $this->assertSame($candidate, (string) $getHistory()[2]['request']->getUri());
    $this->assertSame(hash('sha256', $image), hash_file('sha256', $uri));
  }

  /**
   * Tests that fallback metadata comes from the saved image, not oEmbed data.
   */
  public function testFallbackMetadataUsesActualImageDimensions(): void {
    $original = 'https://i.vimeocdn.com/video/123456789_295x166.jpg';
    $image = $this->imageBytes(295, 166);
    [$media, $source, $getHistory] = $this->createMediaWithServices([
      $this->oembedResponse($original, 295, 221, 640, 360, 'Vimeo'),
      new Response(404),
      new Response(200, ['Content-Type' => 'image/png'], $image),
    ]);

    $uri = $source->getMetadata($media, 'thumbnail_uri');

    $this->assertSame(295, $source->getMetadata($media, 'thumbnail_width'));
    $this->assertSame(166, $source->getMetadata($media, 'thumbnail_height'));
    $this->assertCount(3, $getHistory());
    $this->assertSame(hash('sha256', $image), hash_file('sha256', $uri));
  }

  /**
   * Tests that another source instance reuses the selected local thumbnail.
   */
  public function testSelectedThumbnailIsReusedAcrossSourceInstances(): void {
    $original = 'https://i.ytimg.com/vi/ABCDEFGHIJK/hqdefault.jpg';
    $image = $this->imageBytes(1280, 720);
    [$media, $source, $getHistory] = $this->createMediaWithServices([
      $this->oembedResponse($original, 480, 360, 640, 360),
      new Response(200, ['Content-Type' => 'image/png'], $image),
    ]);

    $uri = $source->getMetadata($media, 'thumbnail_uri');
    $second_source = OEmbed::create(
      $this->container,
      $source->getConfiguration(),
      $source->getPluginId(),
      $source->getPluginDefinition()
    );

    $this->assertSame($uri, $second_source->getMetadata($media, 'thumbnail_uri'));
    $this->assertSame(1280, $second_source->getMetadata($media, 'thumbnail_width'));
    $this->assertSame(720, $second_source->getMetadata($media, 'thumbnail_height'));
    $this->assertCount(2, $getHistory());
  }

  /**
   * Tests metadata requests do not download the thumbnail.
   */
  public function testMetadataRequestsDoNotDownloadThumbnail(): void {
    $original = 'https://i.ytimg.com/vi/ABCDEFGHIJK/hqdefault.jpg';
    [$media, $source, $getHistory] = $this->createMediaWithServices([
      $this->oembedResponse($original, 480, 360, 640, 360, 'YouTube', 'Test author', 'Test title'),
    ]);

    $this->assertSame('Test author', $source->getMetadata($media, 'author_name'));
    $this->assertSame('Test title', $source->getMetadata($media, 'title'));
    $this->assertSame('<iframe></iframe>', $source->getMetadata($media, 'html'));
    $this->assertCount(1, $getHistory());
  }

  /**
   * Tests unsupported thumbnail hosts and providers use the original URL.
   */
  public function testUnsupportedThumbnailsUseOriginalUnchanged(): void {
    $scenarios = [
      [
        'thumbnail' => 'https://images.example.test/video.jpg',
        'provider' => 'YouTube',
        'image' => $this->imageBytes(480, 360),
      ],
      [
        'thumbnail' => 'https://i.ytimg.com/vi/LMNOPQRSTUV/hqdefault.jpg',
        'provider' => 'Dailymotion',
        'image' => $this->imageBytes(640, 360),
      ],
    ];

    foreach ($scenarios as $scenario) {
      [$media, $source, $getHistory] = $this->createMediaWithServices([
        $this->oembedResponse($scenario['thumbnail'], 480, 360, 640, 360, $scenario['provider']),
        new Response(200, ['Content-Type' => 'image/png'], $scenario['image']),
      ]);

      $uri = $source->getMetadata($media, 'thumbnail_uri');

      $this->assertCount(2, $getHistory());
      $this->assertSame($scenario['thumbnail'], (string) $getHistory()[1]['request']->getUri());
      $this->assertSame(hash('sha256', $scenario['image']), hash_file('sha256', $uri));
    }
  }

  /**
   * Tests a portrait poster falling back to the matching numbered maxres one.
   */
  public function testPortraitPosterFallsBackToNumberedMaxres(): void {
    $original = 'https://i.ytimg.com/vi/ZYXWVUTSRQP/hq2.jpg';
    $candidate = 'https://i.ytimg.com/vi/ZYXWVUTSRQP/maxres2.jpg';
    $image = $this->imageBytes(640, 360);
    [$media, $source, $getHistory] = $this->createMediaWithServices([
      $this->oembedResponse($original, 113, 200, 113, 200),
      new Response(404),
      new Response(200, ['Content-Type' => 'image/png'], $image),
    ]);

    $uri = $source->getMetadata($media, 'thumbnail_uri');

    $this->assertSame(640, $source->getMetadata($media, 'thumbnail_width'));
    $this->assertSame(360, $source->getMetadata($media, 'thumbnail_height'));
    $this->assertCount(3, $getHistory());
    $this->assertSame($candidate, (string) $getHistory()[2]['request']->getUri());
    $this->assertSame(hash('sha256', $image), hash_file('sha256', $uri));
  }

  /**
   * Tests that a portrait poster is selected and preserves its dimensions.
   */
  public function testPortraitPosterIsSelected(): void {
    $original = 'https://i.ytimg.com/vi/ABCDEFGHIJK/hqdefault.jpg';
    $candidate = 'https://i.ytimg.com/vi/ABCDEFGHIJK/oardefault.jpg';
    $image = $this->imageBytes(1080, 1920);
    [$media, $source, $getHistory] = $this->createMediaWithServices([
      $this->oembedResponse($original, 480, 360, 360, 640),
      new Response(200, ['Content-Type' => 'image/png'], $image),
    ]);

    $uri = $source->getMetadata($media, 'thumbnail_uri');

    $this->assertSame(1080, $source->getMetadata($media, 'thumbnail_width'));
    $this->assertSame(1920, $source->getMetadata($media, 'thumbnail_height'));
    $this->assertSame($candidate, (string) $getHistory()[1]['request']->getUri());
    $this->assertSame(hash('sha256', $image), hash_file('sha256', $uri));
  }

  /**
   * Tests that numbered portrait posters retain their frame number.
   */
  public function testNumberedPortraitPosterIsSelected(): void {
    $original = 'https://i.ytimg.com/vi/ZYXWVUTSRQP/hq2.jpg';
    $candidate = 'https://i.ytimg.com/vi/ZYXWVUTSRQP/oar2.jpg';
    $image = $this->imageBytes(1080, 1920);
    [$media, $source, $getHistory] = $this->createMediaWithServices([
      $this->oembedResponse($original, 113, 200, 113, 200),
      new Response(200, ['Content-Type' => 'image/png'], $image),
    ]);

    $uri = $source->getMetadata($media, 'thumbnail_uri');

    $this->assertSame(1080, $source->getMetadata($media, 'thumbnail_width'));
    $this->assertSame(1920, $source->getMetadata($media, 'thumbnail_height'));
    $this->assertSame($candidate, (string) $getHistory()[1]['request']->getUri());
    $this->assertSame(hash('sha256', $image), hash_file('sha256', $uri));
  }

  /**
   * Tests Vimeo size removal while preserving its query string.
   */
  public function testVimeoThumbnailKeepsQuery(): void {
    $original = 'https://i.vimeocdn.com/video/123456789_295x166.jpg?token=abc';
    $candidate = 'https://i.vimeocdn.com/video/123456789.jpg?token=abc';
    $image = $this->imageBytes(1427, 803);
    [$media, $source, $getHistory] = $this->createMediaWithServices([
      $this->oembedResponse($original, 295, 166, 640, 360, 'Vimeo'),
      new Response(200, ['Content-Type' => 'image/png'], $image),
    ]);

    $uri = $source->getMetadata($media, 'thumbnail_uri');

    $this->assertSame(1427, $source->getMetadata($media, 'thumbnail_width'));
    $this->assertSame(803, $source->getMetadata($media, 'thumbnail_height'));
    $this->assertSame($candidate, (string) $getHistory()[1]['request']->getUri());
    $this->assertSame(hash('sha256', $image), hash_file('sha256', $uri));
  }

  /**
   * Tests that an existing original file does not block a preferred download.
   */
  public function testExistingOriginalDoesNotBlockPreferredDownload(): void {
    $original = 'https://i.ytimg.com/vi/ABCDEFGHIJK/hqdefault.jpg';
    $candidate = 'https://i.ytimg.com/vi/ABCDEFGHIJK/maxresdefault.jpg';
    $image = $this->imageBytes(1280, 720);
    [$media, $source, $getHistory] = $this->createMediaWithServices([
      $this->oembedResponse($original, 480, 360, 640, 360),
      new Response(200, ['Content-Type' => 'image/png'], $image),
    ], ['thumbnails_directory' => 'public://oembed-existing']);

    $directory = 'public://oembed-existing';
    $this->container->get('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $existing = $directory . '/' . Crypt::hashBase64($original) . '.jpg';
    file_put_contents($existing, $this->imageBytes(480, 360));

    $uri = $source->getMetadata($media, 'thumbnail_uri');

    $this->assertNotSame($existing, $uri);
    $this->assertSame(1280, $source->getMetadata($media, 'thumbnail_width'));
    $this->assertSame($candidate, (string) $getHistory()[1]['request']->getUri());
  }

  /**
   * Creates a media entity and wires a real ResourceFetcher to mocked HTTP.
   *
   * @return array{0: \Drupal\media\MediaInterface, 1: OEmbed, 2: \Closure(): array}
   *   The media entity, source plugin, and request history callback.
   */
  private function createMediaWithServices(array $responses, array $sourceConfiguration = []): array {
    $history = [];
    $handler = new MockHandler($responses);
    $stack = HandlerStack::create($handler);
    $stack->push(Middleware::history($history));
    $client = new Client(['handler' => $stack]);

    $providers = $this->createStub(ProviderRepositoryInterface::class);
    $providers->method('get')->willReturnCallback(static function (string $name): Provider {
      return new Provider($name, 'https://example.com', [['url' => 'https://example.com/oembed']]);
    });
    $resolver = $this->createStub(UrlResolverInterface::class);
    $resolver->method('getResourceUrl')->willReturn('https://example.com/oembed?url=resource');
    $this->container->set('http_client', $client);
    $this->container->set('media.oembed.url_resolver', $resolver);
    $this->container->set('media.oembed.resource_fetcher', new ResourceFetcher(
      $client,
      $providers,
      new MemoryBackend(new Time(new RequestStack()))
    ));

    $configuration = ['thumbnails_directory' => 'public://oembed-thumbnail-test/[date:custom:Y-m]'];
    $configuration = array_replace($configuration, $sourceConfiguration);
    $media_type = $this->createMediaType('oembed:video', [
      'id' => $this->randomMachineName(),
      'label' => 'oEmbed thumbnail test',
      'source_configuration' => $configuration,
    ]);
    $source = $media_type->getSource();
    $source_field = $source->getSourceFieldDefinition($media_type);
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'oEmbed thumbnail test',
      $source_field->getName() => 'https://www.youtube.com/watch?v=ABCDEFGHIJK',
    ]);
    $media->setCreatedTime(1704067200);

    $getHistory = static function () use (&$history): array {
      return $history;
    };

    return [$media, $source, $getHistory];
  }

  /**
   * Builds a JSON oEmbed response.
   */
  private function oembedResponse(string $thumbnail, int $thumbnailWidth, int $thumbnailHeight, int $width, int $height, string $provider = 'YouTube', string $authorName = 'Test author', string $title = 'Test title'): Response {
    return new Response(200, ['Content-Type' => 'application/json'], Json::encode([
      'version' => '1.0',
      'type' => 'video',
      'provider_name' => $provider,
      'author_name' => $authorName,
      'title' => $title,
      'html' => '<iframe></iframe>',
      'width' => $width,
      'height' => $height,
      'thumbnail_url' => $thumbnail,
      'thumbnail_width' => $thumbnailWidth,
      'thumbnail_height' => $thumbnailHeight,
    ]));
  }

  /**
   * Generates a PNG body with the requested dimensions.
   */
  private function imageBytes(int $width, int $height): string {
    $image = imagecreatetruecolor($width, $height);
    $colour = imagecolorallocate($image, 30, 90, 140);
    imagefill($image, 0, 0, $colour);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();

    return (string) $bytes;
  }

}
