<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Tests\Core\Database\Stub\StubLegacyConnection;
use Drupal\Tests\Core\Database\Stub\StubPDO;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Tests deprecations of the Connection class.
 *
 * @group Database
 */
class ConnectionLegacyTest extends UnitTestCase {

  /**
   * Tests that missing IdentifierHandler throws a deprecation.
   */
  #[IgnoreDeprecations]
  public function testMissingIdentifierHandler(): void {
    $this->expectDeprecation('Not passing an IdentifierHandler object to Drupal\\Core\\Database\\Connection::__construct() is deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. See https://www.drupal.org/node/3513282');
    $connection = new StubLegacyConnection($this->createMock(StubPDO::class), ['prefix' => 'foo']);
    $this->assertInstanceOf(StubLegacyConnection::class, $connection);
  }

  /**
   * Deprecation of Connection::$prefix.
   */
  #[IgnoreDeprecations]
  public function testPrefix(): void {
    $this->expectDeprecation('Accessing Connection::$prefix is deprecated in drupal:11.3.0 and the property is removed from drupal:13.0.0. Use IdentifierHandler methods instead. See https://www.drupal.org/node/3513282');
    $connection = new StubLegacyConnection($this->createMock(StubPDO::class), ['prefix' => 'foo']);
    $this->assertSame('foo', $connection->prefix);
    $this->expectDeprecation('Accessing Connection::$prefix is deprecated in drupal:11.3.0 and the property is removed from drupal:13.0.0. Use IdentifierHandler methods instead. See https://www.drupal.org/node/3513282');
    $connection->prefix = 'bar';
    $this->assertSame('bar', $connection->prefix);
  }

  /**
   * Deprecation of Connection::$escapedTables.
   */
  #[IgnoreDeprecations]
  public function testEscapedTables(): void {
    $this->expectDeprecation('Accessing Connection::$escapedTables is deprecated in drupal:11.3.0 and the property is removed from drupal:13.0.0. Use IdentifierHandler methods instead. See https://www.drupal.org/node/3513282');
    $connection = new StubLegacyConnection($this->createMock(StubPDO::class), ['prefix' => 'foo']);
    $this->assertSame([], $connection->escapedTables);
    $this->expectDeprecation('Accessing Connection::$escapedTables is deprecated in drupal:11.3.0 and the property is removed from drupal:13.0.0. Use IdentifierHandler methods instead. See https://www.drupal.org/node/3513282');
    $connection->escapedTables = 'bar';
    $this->assertSame([], $connection->escapedTables);
  }

  /**
   * Deprecation of Connection::$identifierQuotes.
   */
  #[IgnoreDeprecations]
  public function testIdentifierQuotes(): void {
    $this->expectDeprecation('Accessing Connection::$identifierQuotes is deprecated in drupal:11.3.0 and the property is removed from drupal:13.0.0. Use IdentifierHandler methods instead. See https://www.drupal.org/node/3513282');
    $connection = new StubLegacyConnection($this->createMock(StubPDO::class), ['prefix' => 'foo']);
    $this->assertSame(['"', '"'], $connection->identifierQuotes);
    $this->expectDeprecation('Accessing Connection::$identifierQuotes is deprecated in drupal:11.3.0 and the property is removed from drupal:13.0.0. Use IdentifierHandler methods instead. See https://www.drupal.org/node/3513282');
    $connection->identifierQuotes = 'bar';
    $this->assertSame(['"', '"'], $connection->identifierQuotes);
  }

  /**
   * Deprecation of Connection::$tablePlaceholderReplacements.
   */
  #[IgnoreDeprecations]
  public function testTablePlaceholderReplacements(): void {
    $this->expectDeprecation('Accessing Connection::$tablePlaceholderReplacements is deprecated in drupal:11.3.0 and the property is removed from drupal:13.0.0. Use IdentifierHandler methods instead. See https://www.drupal.org/node/3513282');
    $connection = new StubLegacyConnection($this->createMock(StubPDO::class), ['prefix' => 'foo']);
    $this->assertSame(['"foo', '"'], $connection->tablePlaceholderReplacements);
    $this->expectDeprecation('Accessing Connection::$tablePlaceholderReplacements is deprecated in drupal:11.3.0 and the property is removed from drupal:13.0.0. Use IdentifierHandler methods instead. See https://www.drupal.org/node/3513282');
    $connection->tablePlaceholderReplacements = 'bar';
    $this->assertSame(['"foo', '"'], $connection->tablePlaceholderReplacements);
  }

}
