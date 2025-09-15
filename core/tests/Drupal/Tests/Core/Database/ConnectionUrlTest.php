<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\Connection;
use Drupal\Tests\UnitTestCase;

/**
 * Tests database connection URL creation and parsing.
 *
 * @group Database
 */
class ConnectionUrlTest extends UnitTestCase {

  /**
   * Tests that createUrlFromConnectionOptions preserves unix_socket parameter.
   */
  public function testCreateUrlFromConnectionOptionsPreservesUnixSocket(): void {
    $connection_options = [
      'driver' => 'mysql',
      'username' => 'test_user',
      'password' => 'test_pass',
      'host' => 'localhost',
      'database' => 'test_db',
      'unix_socket' => '/tmp/mysql.sock',
      'module' => 'mysql',
    ];

    $url = Connection::createUrlFromConnectionOptions($connection_options);

    // Parse the URL to verify components.
    $parsed = parse_url($url);
    $this->assertEquals('mysql', $parsed['scheme']);
    $this->assertEquals('test_user:test_pass@localhost', $parsed['user'] . ':' . $parsed['pass'] . '@' . $parsed['host']);
    $this->assertEquals('/test_db', $parsed['path']);

    // Parse query parameters.
    parse_str($parsed['query'], $query_params);

    // Verify that unix_socket parameter is preserved.
    $this->assertArrayHasKey('unix_socket', $query_params);
    $this->assertEquals('/tmp/mysql.sock', $query_params['unix_socket']);

    // Verify that module parameter is also preserved.
    $this->assertArrayHasKey('module', $query_params);
    $this->assertEquals('mysql', $query_params['module']);
  }

  /**
   * Tests that createUrlFromConnectionOptions preserves relevant parameters.
   */
  public function testCreateUrlFromConnectionOptionsPreservesRelevantParameters(): void {
    $connection_options = [
      'driver' => 'mysql',
      'username' => 'user',
      'host' => 'localhost',
      'database' => 'db',
      'unix_socket' => '/var/lib/mysql/mysql.sock',
      'module' => 'mysql',
    ];

    $url = Connection::createUrlFromConnectionOptions($connection_options);
    $parsed = parse_url($url);
    parse_str($parsed['query'], $query_params);

    // Key database connection parameters should be preserved.
    $expected_params = [
      'module' => 'mysql',
      'unix_socket' => '/var/lib/mysql/mysql.sock',
    ];

    foreach ($expected_params as $key => $expected_value) {
      $this->assertArrayHasKey($key, $query_params, "Parameter '$key' should be preserved");
      $this->assertEquals($expected_value, $query_params[$key], "Parameter '$key' should have correct value");
    }
  }

  /**
   * Tests that createUrlFromConnectionOptions works without query parameters.
   */
  public function testCreateUrlFromConnectionOptionsWithoutParameters(): void {
    $connection_options = [
      'driver' => 'mysql',
      'username' => 'user',
      'host' => 'localhost',
      'database' => 'db',
    ];

    $url = Connection::createUrlFromConnectionOptions($connection_options);
    $parsed = parse_url($url);

    // Should not have a query string when no parameters are present.
    $this->assertArrayNotHasKey('query', $parsed, 'URL should not have query string when no parameters are present');
  }

  /**
   * Tests URL reconstruction preserves prefix fragments.
   */
  public function testCreateUrlFromConnectionOptionsPreservesPrefix(): void {
    $connection_options = [
      'driver' => 'mysql',
      'host' => 'localhost',
      'database' => 'db',
      'prefix' => 'test_prefix_',
      'unix_socket' => '/tmp/socket',
      'module' => 'mysql',
    ];

    $url = Connection::createUrlFromConnectionOptions($connection_options);
    $parsed = parse_url($url);

    // Verify prefix is in fragment.
    $this->assertEquals('test_prefix_', $parsed['fragment']);

    // Verify query parameters are still present.
    parse_str($parsed['query'], $query_params);
    $this->assertArrayHasKey('unix_socket', $query_params);
    $this->assertArrayHasKey('module', $query_params);
  }

  /**
   * Tests that createConnectionOptionsFromUrl preserves unix_socket parameter.
   *
   * This tests the reverse direction: URL → connection options.
   */
  public function testCreateConnectionOptionsFromUrlPreservesUnixSocket(): void {
    $url = 'mysql://test_user:test_pass@localhost/test_db?unix_socket=%2Ftmp%2Fmysql.sock&module=mysql';

    $connection_options = Connection::createConnectionOptionsFromUrl($url, NULL);

    // Verify basic connection info.
    $this->assertEquals('mysql', $connection_options['driver']);
    $this->assertEquals('test_user', $connection_options['username']);
    $this->assertEquals('test_pass', $connection_options['password']);
    $this->assertEquals('localhost', $connection_options['host']);
    $this->assertEquals('test_db', $connection_options['database']);

    // Verify query parameters are preserved.
    $this->assertArrayHasKey('unix_socket', $connection_options);
    $this->assertEquals('/tmp/mysql.sock', $connection_options['unix_socket']);
    $this->assertArrayHasKey('module', $connection_options);
    $this->assertEquals('mysql', $connection_options['module']);
  }

  /**
   * Tests bidirectional URL conversion preserves unix_socket.
   *
   * This ensures URL → options → URL round-trip works correctly.
   */
  public function testBidirectionalUrlConversionPreservesUnixSocket(): void {
    $original_url = 'mysql://user:pass@localhost/db?unix_socket=%2Fvar%2Flib%2Fmysql%2Fmysql.sock&module=mysql';

    // URL → connection options → URL.
    $options = Connection::createConnectionOptionsFromUrl($original_url, NULL);
    $reconstructed_url = Connection::createUrlFromConnectionOptions($options);

    // Parse both URLs to compare components.
    $original_parsed = parse_url($original_url);
    $reconstructed_parsed = parse_url($reconstructed_url);

    // Compare basic components.
    $this->assertEquals($original_parsed['scheme'], $reconstructed_parsed['scheme']);
    $this->assertEquals($original_parsed['user'], $reconstructed_parsed['user']);
    $this->assertEquals($original_parsed['pass'], $reconstructed_parsed['pass']);
    $this->assertEquals($original_parsed['host'], $reconstructed_parsed['host']);
    $this->assertEquals($original_parsed['path'], $reconstructed_parsed['path']);

    // Compare query parameters.
    parse_str($original_parsed['query'], $original_params);
    parse_str($reconstructed_parsed['query'], $reconstructed_params);

    $this->assertEquals($original_params['unix_socket'], $reconstructed_params['unix_socket']);
    $this->assertEquals($original_params['module'], $reconstructed_params['module']);
  }

  /**
   * Tests SIMPLETEST_DB URL parsing (simulates installer scenario).
   *
   * This tests the scenario where SIMPLETEST_DB contains unix_socket parameter
   * that needs to be preserved through URL conversion processes.
   */
  public function testSimpleTestDbUrlParsing(): void {
    $simpletest_url = 'mysql://drupal@localhost/drupal_test?unix_socket=%2Ftmp%2Fmysql.sock&module=mysql';

    // This simulates what happens when SIMPLETEST_DB gets parsed.
    $connection_options = Connection::createConnectionOptionsFromUrl($simpletest_url, NULL);

    // Verify unix_socket is correctly parsed.
    $this->assertArrayHasKey('unix_socket', $connection_options);
    $this->assertEquals('/tmp/mysql.sock', $connection_options['unix_socket']);

    // This simulates what happens when child processes get their database URL.
    $child_process_url = Connection::createUrlFromConnectionOptions($connection_options);
    $child_connection_options = Connection::createConnectionOptionsFromUrl($child_process_url, NULL);

    // Child process should also get the unix_socket parameter.
    $this->assertArrayHasKey('unix_socket', $child_connection_options);
    $this->assertEquals('/tmp/mysql.sock', $child_connection_options['unix_socket']);
  }

}
