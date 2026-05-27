<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Http;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Cacheable Exception.
 */
#[Group('Http')]
class CacheableExceptionTest extends UnitTestCase {

  /**
   * Tests cacheable http exception.
   *
   * @legacy-covers \Drupal\Core\Http\Exception\CacheableHttpException
   */
  public function testCacheableHttpException(): void {
    $exception = new \Drupal\Core\Http\Exception\CacheableHttpException((new CacheableMetadata())->setCacheContexts(['route']), 500, 'test message', NULL, ['X-Drupal-Exception' => 'Test'], 123);
    $this->assertSame(['route'], $exception->getCacheContexts());
    $this->assertSame(500, $exception->getStatusCode());
    $this->assertSame('test message', $exception->getMessage());
    $this->assertSame(['X-Drupal-Exception' => 'Test'], $exception->getHeaders());
    $this->assertSame(123, $exception->getCode());
  }

  /**
 * Tests exceptions.
 */
  #[DataProvider('providerTestExceptions')]
  public function testExceptions(int $status_code, string $class, string|int|array|null $argument = NULL, array $expected_headers = []): void {
    $cacheable_metadata = (new CacheableMetadata())->setCacheContexts(['route']);
    $message = "$class test message";
    $previous = new class('Error of PHP 7+') extends \Error {};
    if ($argument) {
      $exception = new $class($cacheable_metadata, $argument, $message, $previous, 123);
    }
    else {
      $exception = new $class($cacheable_metadata, $message, $previous, 123);
    }
    $this->assertSame(['route'], $exception->getCacheContexts());
    $this->assertSame($message, $exception->getMessage());
    $this->assertSame($status_code, $exception->getStatusCode());
    $this->assertSame($expected_headers, $exception->getHeaders());
    $this->assertSame($previous, $exception->getPrevious());
    $this->assertSame(123, $exception->getCode());
  }

  public static function providerTestExceptions(): array {
    return [
      [400, \Drupal\Core\Http\Exception\CacheableBadRequestHttpException::class],
      [401, \Drupal\Core\Http\Exception\CacheableUnauthorizedHttpException::class, 'test challenge',
        [
          'WWW-Authenticate' => 'test challenge',
        ],
      ],
      [403, \Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException::class],
      [404, \Drupal\Core\Http\Exception\CacheableNotFoundHttpException::class],
      [405, \Drupal\Core\Http\Exception\CacheableMethodNotAllowedHttpException::class, ['POST', 'PUT'],
        [
          'Allow' => 'POST, PUT',
        ],
      ],
      [406, \Drupal\Core\Http\Exception\CacheableNotAcceptableHttpException::class],
      [409, \Drupal\Core\Http\Exception\CacheableConflictHttpException::class],
      [410, \Drupal\Core\Http\Exception\CacheableGoneHttpException::class],
      [411, \Drupal\Core\Http\Exception\CacheableLengthRequiredHttpException::class],
      [412, \Drupal\Core\Http\Exception\CacheablePreconditionFailedHttpException::class],
      [415, \Drupal\Core\Http\Exception\CacheableUnsupportedMediaTypeHttpException::class],
      [422, \Drupal\Core\Http\Exception\CacheableUnprocessableEntityHttpException::class],
      [428, \Drupal\Core\Http\Exception\CacheablePreconditionRequiredHttpException::class],
      [429, \Drupal\Core\Http\Exception\CacheableTooManyRequestsHttpException::class, 60, ['Retry-After' => 60]],
      [503, \Drupal\Core\Http\Exception\CacheableServiceUnavailableHttpException::class, 60, ['Retry-After' => 60]],
    ];
  }

}
