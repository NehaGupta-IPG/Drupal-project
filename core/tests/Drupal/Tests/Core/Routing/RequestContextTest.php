<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Routing;

use Drupal\Core\Routing\RequestContext;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests Drupal\Core\Routing\RequestContext.
 */
#[CoversClass(RequestContext::class)]
#[Group('Routing')]
class RequestContextTest extends UnitTestCase {

  /**
   * Tests that an encoded front controller is not stored as the base URL.
   *
   * @legacy-covers ::fromRequest
   */
  public function testFromRequestStripsEncodedFrontController(): void {
    $request = new Request([], [], [], [], [], [
      'REQUEST_URI' => '/index%2Ephp/node/1',
      'SCRIPT_NAME' => '/index.php',
      'SCRIPT_FILENAME' => '/var/www/html/index.php',
      'PHP_SELF' => '/index.php/node/1',
      'REQUEST_METHOD' => 'GET',
      'HTTP_HOST' => 'example.com',
      'SERVER_NAME' => 'example.com',
      'SERVER_PORT' => '80',
    ]);
    $context = new RequestContext();
    $context->fromRequest($request);
    $this->assertSame('', $context->getBaseUrl());
  }

  /**
   * Tests that literal unclean URLs keep the front controller in the base URL.
   *
   * @legacy-covers ::fromRequest
   */
  public function testFromRequestPreservesLiteralUncleanUrl(): void {
    $request = new Request([], [], [], [], [], [
      'REQUEST_URI' => '/index.php/node/1',
      'SCRIPT_NAME' => '/index.php',
      'SCRIPT_FILENAME' => '/var/www/html/index.php',
      'PHP_SELF' => '/index.php/node/1',
      'REQUEST_METHOD' => 'GET',
      'HTTP_HOST' => 'example.com',
      'SERVER_NAME' => 'example.com',
      'SERVER_PORT' => '80',
    ]);
    $context = new RequestContext();
    $context->fromRequest($request);
    $this->assertSame('/index.php', $context->getBaseUrl());
  }

}
