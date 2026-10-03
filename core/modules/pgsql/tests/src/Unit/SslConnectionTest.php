<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Unit;

use Drupal\pgsql\Driver\Database\pgsql\Connection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the SSL options of Drupal\pgsql\Driver\Database\pgsql\Connection.
 */
#[CoversClass(Connection::class)]
#[Group('Database')]
class SslConnectionTest extends UnitTestCase {

  /**
   * Tests that SSL connection options are added to the PostgreSQL DSN.
   */
  public function testSslOptionsInDsn(): void {
    $options = [
      'host' => 'localhost',
      'database' => 'drupal',
      'port' => 5432,
      'sslmode' => 'verify-full',
      'sslcert' => '/path/client.crt',
      'sslkey' => '/path/client.key',
      'sslrootcert' => '/path/root.crt',
    ];

    $method = new \ReflectionMethod(Connection::class, 'buildDsn');
    $dsn = $method->invoke(NULL, $options);

    $this->assertSame(
      'pgsql:host=localhost dbname=drupal port=5432 client_encoding=UTF8 sslmode=verify-full sslcert=/path/client.crt sslkey=/path/client.key sslrootcert=/path/root.crt',
      $dsn,
    );
  }

}
