<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the PostgreSQL SSL connection options.
 *
 * Tests that need a TLS-enabled server are skipped when the server has SSL
 * disabled. The verify-full test additionally requires the
 * SIMPLETEST_PGSQL_SSLROOTCERT environment variable to point to the root
 * certificate that signed the server certificate.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class SslConnectionTest extends DriverSpecificKernelTestBase {

  /**
   * The connection key used for the SSL test connections.
   */
  protected const CONNECTION_KEY = 'ssl_test';

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    Database::closeConnection('default', static::CONNECTION_KEY);
    Database::removeConnection(static::CONNECTION_KEY);
    parent::tearDown();
  }

  /**
   * Tests that sslmode=disable results in an unencrypted connection.
   */
  public function testSslModeDisable(): void {
    $connection = $this->getSslConnection(['sslmode' => 'disable']);
    $this->assertFalse($this->isSslConnection($connection));
  }

  /**
   * Tests that sslmode=require results in an encrypted connection.
   */
  public function testSslModeRequire(): void {
    $this->skipIfServerSslDisabled();

    $connection = $this->getSslConnection(['sslmode' => 'require']);
    $this->assertTrue($this->isSslConnection($connection));
  }

  /**
   * Tests that sslmode=verify-full with a valid root certificate connects.
   */
  public function testSslModeVerifyFull(): void {
    $this->skipIfServerSslDisabled();
    $root_cert = getenv('SIMPLETEST_PGSQL_SSLROOTCERT');
    if (!$root_cert) {
      $this->markTestSkipped('Set SIMPLETEST_PGSQL_SSLROOTCERT to the root certificate of the PostgreSQL server to run this test.');
    }

    $connection = $this->getSslConnection([
      'sslmode' => 'verify-full',
      'sslrootcert' => $root_cert,
    ]);
    $this->assertTrue($this->isSslConnection($connection));
  }

  /**
   * Tests that sslrootcert is used when verifying the server certificate.
   */
  public function testSslRootCertIsUsed(): void {
    $this->skipIfServerSslDisabled();

    $this->expectException(\PDOException::class);
    $this->expectExceptionMessageMatches('/root certificate file .* does not exist/');
    $this->getSslConnection([
      'sslmode' => 'verify-full',
      'sslrootcert' => '/nonexistent/root.crt',
    ]);
  }

  /**
   * Opens a new connection with the given SSL options.
   *
   * @param array $ssl_options
   *   The SSL connection options to add to the default connection info.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection.
   */
  protected function getSslConnection(array $ssl_options): Connection {
    $connection_info = Database::getConnectionInfo()['default'];
    Database::addConnectionInfo(static::CONNECTION_KEY, 'default', $ssl_options + $connection_info);
    return Database::getConnection('default', static::CONNECTION_KEY);
  }

  /**
   * Check if the given connection is encrypted.
   */
  protected function isSslConnection(Connection $connection): bool {
    return (bool) $connection->query('SELECT ssl FROM pg_stat_ssl WHERE pid = pg_backend_pid()')->fetchField();
  }

  /**
   * Skips the test when the PostgreSQL server does not have SSL enabled.
   */
  protected function skipIfServerSslDisabled(): void {
    if ($this->connection->query('SHOW ssl')->fetchField() !== 'on') {
      $this->markTestSkipped('The PostgreSQL server does not have SSL enabled.');
    }
  }

}
