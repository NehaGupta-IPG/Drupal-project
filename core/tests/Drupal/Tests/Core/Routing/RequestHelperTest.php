<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Routing;

use Drupal\Core\Routing\RequestHelper;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests Drupal\Core\Routing\RequestHelper.
 */
#[CoversClass(RequestHelper::class)]
#[Group('Routing')]
class RequestHelperTest extends UnitTestCase {

  /**
   * Tests whether a request is detected as a clean URL.
   *
   * @legacy-covers ::isCleanUrl
   */
  #[DataProvider('providerTestIsCleanUrl')]
  public function testIsCleanUrl(array $server, bool $expected): void {
    $request = new Request([], [], [], [], [], $server);
    $this->assertSame($expected, RequestHelper::isCleanUrl($request));
  }

  /**
   * Provides test data for testIsCleanUrl().
   *
   * @return array
   *   An array of test cases.
   */
  public static function providerTestIsCleanUrl(): array {
    return [
      'clean URL' => [
        self::server('/node/1', '/index.php', '/index.php'),
        TRUE,
      ],
      'clean URL homepage' => [
        self::server('/', '/index.php', '/index.php'),
        TRUE,
      ],
      'query string containing index.php' => [
        self::server('/node/1?file=index.php', '/index.php', '/index.php'),
        TRUE,
      ],
      'literal unclean URL' => [
        self::server('/index.php/node/1', '/index.php', '/index.php/node/1'),
        FALSE,
      ],
      'literal unclean homepage' => [
        self::server('/index.php', '/index.php', '/index.php'),
        FALSE,
      ],
      'encoded front controller uppercase' => [
        self::server('/index%2Ephp/node/1', '/index.php', '/index.php/node/1'),
        FALSE,
      ],
      'encoded front controller lowercase' => [
        self::server('/index%2ephp/user/login', '/index.php', '/index.php'),
        FALSE,
      ],
      'encoded front controller homepage' => [
        self::server('/index%2Ephp', '/index.php', '/index.php'),
        FALSE,
      ],
      'subdirectory clean URL' => [
        self::server('/subdir/node/1', '/subdir/index.php', '/subdir/index.php'),
        TRUE,
      ],
      'subdirectory literal unclean URL' => [
        self::server('/subdir/index.php/node/1', '/subdir/index.php', '/subdir/index.php/node/1'),
        FALSE,
      ],
      'subdirectory encoded front controller' => [
        self::server('/subdir/index%2Ephp/node/1', '/subdir/index.php', '/subdir/index.php/node/1'),
        FALSE,
      ],
    ];
  }

  /**
   * Builds a $_SERVER array that matches a PHP front-controller request.
   *
   * @param string $request_uri
   *   The request URI, including a leading slash.
   * @param string $script_name
   *   The SCRIPT_NAME value.
   * @param string $php_self
   *   The PHP_SELF value.
   *
   * @return array
   *   A $_SERVER array for Symfony Request.
   */
  protected static function server(string $request_uri, string $script_name, string $php_self): array {
    return [
      'REQUEST_URI' => $request_uri,
      'SCRIPT_NAME' => $script_name,
      'SCRIPT_FILENAME' => '/var/www/html/index.php',
      'PHP_SELF' => $php_self,
      'REQUEST_METHOD' => 'GET',
      'HTTP_HOST' => 'example.com',
      'SERVER_NAME' => 'example.com',
      'SERVER_PORT' => '80',
    ];
  }

}
