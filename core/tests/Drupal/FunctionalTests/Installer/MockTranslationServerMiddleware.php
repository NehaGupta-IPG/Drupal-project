<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Mocks translation server requests during installer tests.
 */
class MockTranslationServerMiddleware {

  /**
   * The base URI used for mocked translation requests.
   */
  public const MOCK_SERVER_BASE_URI = 'https://translations.example.test/';

  /**
   * Number of intercepted HEAD requests.
   */
  protected static int $headRequestCount = 0;

  /**
   * Number of intercepted GET requests.
   */
  protected static int $getRequestCount = 0;

  /**
   * URIs requested via GET.
   *
   * @var string[]
   */
  protected static array $getRequestUris = [];

  /**
   * The .po payload returned for successful GET requests.
   */
  protected static string $translationBody = '';

  /**
   * Resets middleware state for a test run.
   */
  public static function reset(string $translation_body): void {
    self::$headRequestCount = 0;
    self::$getRequestCount = 0;
    self::$getRequestUris = [];
    self::$translationBody = $translation_body;
  }

  /**
   * Gets the number of intercepted HEAD requests.
   */
  public static function getHeadRequestCount(): int {
    return self::$headRequestCount;
  }

  /**
   * Gets the number of intercepted GET requests.
   */
  public static function getGetRequestCount(): int {
    return self::$getRequestCount;
  }

  /**
   * Gets intercepted GET request URIs.
   *
   * @return string[]
   *   The intercepted request URIs.
   */
  public static function getGetRequestUris(): array {
    return self::$getRequestUris;
  }

  /**
   * HTTP middleware callback.
   */
  public function __invoke(): \Closure {
    return static function (callable $handler) {
      return static function (RequestInterface $request, array $options) use ($handler) {
        $uri = (string) $request->getUri();
        if (!str_starts_with($uri, self::MOCK_SERVER_BASE_URI)) {
          return $handler($request, $options);
        }

        if ($request->getMethod() === 'HEAD') {
          self::$headRequestCount++;
          return Create::promiseFor(new Response(200));
        }

        if ($request->getMethod() === 'GET') {
          self::$getRequestCount++;
          self::$getRequestUris[] = $uri;
          return Create::promiseFor(new Response(200, ['Content-Type' => 'text/plain'], self::$translationBody));
        }

        return $handler($request, $options);
      };
    };
  }

}
