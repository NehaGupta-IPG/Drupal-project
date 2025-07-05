<?php

declare(strict_types=1);

namespace Drupal\Tests\sqlite\Unit;

use Drupal\Core\Database\Exception\IdentifierException;
use Drupal\Core\Database\Identifier\IdentifierType;
use Drupal\sqlite\Driver\Database\sqlite\IdentifierHandler;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests SQLite database identifiers.
 */
#[CoversClass(IdentifierHandler::class)]
#[Group('Database')]
class IdentifierTest extends UnitTestCase {

  // cSpell:disable

  /**
   * Data provider for testTable.
   *
   * @return array
   *   An associative array of test case data.
   */
  public static function providerTable(): array {
    return [
      'No prefix' => [
        'identifier' => 'nocase',
        'prefix' => '',
        'expectedCanonical' => 'nocase',
        'expectedMachine' => '"nocase"',
      ],
      'Prefix' => [
        'identifier' => 'nocase',
        'prefix' => 'foobar',
        'expectedCanonical' => 'foobar.nocase',
        'expectedMachine' => '"foobar"."nocase"',
      ],
      'No prefix, camelCase' => [
        'identifier' => 'camelCase',
        'prefix' => '',
        'expectedCanonical' => 'camelCase',
        'expectedMachine' => '"camelCase"',
      ],
      'Prefix, camelCase' => [
        'identifier' => 'camelCase',
        'prefix' => 'foobar',
        'expectedCanonical' => 'foobar.camelCase',
        'expectedMachine' => '"foobar"."camelCase"',
      ],
      'No prefix, backtick' => [
        'identifier' => '`backtick`',
        'prefix' => '',
        'expectedCanonical' => 'backtick',
        'expectedMachine' => '"backtick"',
      ],
      'No prefix, brackets' => [
        'identifier' => '[brackets]',
        'prefix' => '',
        'expectedCanonical' => 'brackets',
        'expectedMachine' => '"brackets"',
      ],
      'No prefix, remove slash' => [
        'identifier' => 'no/case',
        'prefix' => '',
        'expectedCanonical' => 'nocase',
        'expectedMachine' => '"nocase"',
      ],
      'No prefix, remove quote' => [
        'identifier' => 'no"case',
        'prefix' => '',
        'expectedCanonical' => 'nocase',
        'expectedMachine' => '"nocase"',
      ],
      'No prefix, too long table name' => [
        'identifier' => 'VeryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'prefix' => '',
        'expectedCanonical' => '',
        'expectedMachine' => '',
        'expectedException' => 'The length of the table identifier \'VeryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar\' once canonicalized to \'VeryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar\' is invalid (maximum allowed: 128)',
      ],
      'Prefix too long to fit' => [
        'identifier' => 'nocase',
        'prefix' => 'VeryVeryVeryVeryVeryVeryVeryVeryVeryVeryLongLongLongLongLongLongLongVeryVeryVeryVeryVeryVeryVeryVeryLongLongLongLongLongLongLongPrefix',
        'expectedCanonical' => '',
        'expectedMachine' => '',
        'expectedException' => 'The length of the schema identifier \'VeryVeryVeryVeryVeryVeryVeryVeryVeryVeryLongLongLongLongLongLongLongVeryVeryVeryVeryVeryVeryVeryVeryLongLongLongLongLongLongLongPrefix\' once canonicalized to \'VeryVeryVeryVeryVeryVeryVeryVeryVeryVeryLongLongLongLongLongLongLongVeryVeryVeryVeryVeryVeryVeryVeryLongLongLongLongLongLongLongPrefix\' is invalid (maximum allowed: 128)',
      ],
      // Sometimes, table names are following the pattern database.schema.table.
      'Fully qualified - too many parts: 4' => [
        'identifier' => 'Chowra.Teressa.Bompuka.Katchal',
        'prefix' => '',
        'expectedCanonical' => '',
        'expectedMachine' => '',
        'expectedException' => 'The table identifier \'Chowra.Teressa.Bompuka.Katchal\' does not comply with the syntax [database.][schema.]table',
      ],
      'Fully qualified - too many parts: SQLite not supporting \'database\'' => [
        'identifier' => 'Chowra.Teressa.Bompuka',
        'prefix' => '',
        'expectedCanonical' => '',
        'expectedMachine' => '',
        'expectedException' => 'SQLite does not support the syntax [database.][schema.]table for the table identifier \'Chowra.Teressa.Bompuka\'. Avoid specifying the \'database\' part',
      ],
      'Fully qualified - no prefix' => [
        'identifier' => '"Nancowry"."Tillangchong"',
        'prefix' => '',
        'expectedCanonical' => 'Nancowry.Tillangchong',
        'expectedMachine' => '"Nancowry"."Tillangchong"',
      ],
      'Fully qualified - prefix' => [
        'identifier' => '"Nancowry"."Tillangchong"',
        'prefix' => 'foobar',
        'expectedCanonical' => 'Nancowry.Tillangchong',
        'expectedMachine' => '"Nancowry"."Tillangchong"',
      ],
      'Fully qualified - prefix not duplicated' => [
        'identifier' => 'Nancowry.foobarTillangchong',
        'prefix' => 'foobar',
        'expectedCanonical' => 'Nancowry.foobarTillangchong',
        'expectedMachine' => '"Nancowry"."foobarTillangchong"',
      ],
      'Fully qualified - remove not canonical characters' => [
        'identifier' => '!Nancowry?.$Tillangchong%%%',
        'prefix' => '',
        'expectedCanonical' => 'Nancowry.Tillangchong',
        'expectedMachine' => '"Nancowry"."Tillangchong"',
      ],
      'Fully qualified - invalid schema name length' => [
        'identifier' => 'VeryVeryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar.Trinket',
        'prefix' => '',
        'expectedCanonical' => '',
        'expectedMachine' => '',
        'expectedException' => 'The length of the schema identifier \'VeryVeryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar\' once canonicalized to \'VeryVeryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar\' is invalid (maximum allowed: 128)',
      ],
      'Fully qualified - invalid table name length' => [
        'identifier' => 'Camorta.VeryVeryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'prefix' => '',
        'expectedCanonical' => '',
        'expectedMachine' => '',
        'expectedException' => 'The length of the table identifier \'VeryVeryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar\' once canonicalized to \'VeryVeryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryVeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar\' is invalid (maximum allowed: 128)',
      ],
    ];
  }

  // cSpell:enable

  /**
   * Tests table identifiers.
   */
  #[DataProvider('providerTable')]
  public function testTable(string $identifier, string $prefix = '', ?string $expectedCanonical = '', ?string $expectedMachine = '', ?string $expectedException = NULL): void {
    $handler = new IdentifierHandler($prefix);
    if ($expectedException) {
      $this->expectException(IdentifierException::class);
      $this->expectExceptionMessage($expectedException);
    }
    $this->assertSame($expectedCanonical, $handler->table($identifier)->canonical());
    $this->assertSame($expectedMachine, $handler->table($identifier)->forMachine());
    // The machine name includes the quote characters so we need to subtract
    // those from the length.
    $this->assertLessThanOrEqual($handler->getMaxLength(IdentifierType::Table), strlen($handler->table($identifier)->machineName), 'Invalid machine table length.');
  }

}
