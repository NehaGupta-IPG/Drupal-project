<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Unit;

use Drupal\Core\Database\Exception\IdentifierException;
use Drupal\Core\Database\Identifier\IdentifierType;
use Drupal\mysql\Driver\Database\mysql\IdentifierHandler;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests MySQL database identifiers.
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
        'expectedCanonical' => 'nocase',
        'expectedMachine' => '"foobarnocase"',
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
        'expectedCanonical' => 'camelCase',
        'expectedMachine' => '"foobarcamelCase"',
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
      'No prefix, shortened machine name' => [
        'identifier' => 'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'prefix' => '',
        'expectedCanonical' => 'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'expectedMachine' => '"VeryVeryVeryVeryHungryHungr25fa0c3753ngryHungryHungryCaterpillar"',
      ],
      'Prefix, shortened machine name' => [
        'identifier' => 'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'prefix' => 'foobar',
        'expectedCanonical' => 'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'expectedMachine' => '"foobarVeryVeryVeryVeryHungryHu25fa0c3753yHungryHungryCaterpillar"',
      ],
      'Prefix one less than overflow, shortened machine name' => [
        'identifier' => 'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'prefix' => 'prefix____123456789012345678901234567890123456789',
        'expectedCanonical' => 'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'expectedMachine' => '"prefix____123456789012345678901234567890123456789Ve25fa0c3753lar"',
      ],
      'Prefix just right, shortened machine name' => [
        'identifier' => 'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'prefix' => 'prefix____1234567890123456789012345678901234567890',
        'expectedCanonical' => 'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'expectedMachine' => '"prefix____1234567890123456789012345678901234567890Ve25fa0c3753ar"',
      ],
      'Prefix one more than overflow, shortened machine name' => [
        'identifier' => 'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'prefix' => 'prefix____12345678901234567890123456789012345678901',
        'expectedCanonical' => 'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'expectedMachine' => '',
        'expectedException' => 'Table canonical identifier \'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar\' cannot be converted into a machine identifier; prefix \'prefix____12345678901234567890123456789012345678901\'',
      ],
      'Prefix too long to fit, table does not require shortening' => [
        'identifier' => 'nocase',
        'prefix' => 'VeryVeryVeryVeryVeryVeryVeryVeryLongLongLongLongLongLongLongPrefix',
        'expectedCanonical' => 'nocase',
        'expectedMachine' => '',
        'expectedException' => 'Table canonical identifier \'nocase\' cannot be converted into a machine identifier; prefix \'VeryVeryVeryVeryVeryVeryVeryVeryLongLongLongLongLongLongLongPrefix\'',
      ],
      'Prefix too long to fit, table requires shortening' => [
        'identifier' => 'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'prefix' => 'VeryVeryVeryVeryVeryVeryVeryVeryLongLongLongLongLongLongLongPrefix',
        'expectedCanonical' => 'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'expectedMachine' => '',
        'expectedException' => 'Table canonical identifier \'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar\' cannot be converted into a machine identifier; prefix \'VeryVeryVeryVeryVeryVeryVeryVeryLongLongLongLongLongLongLongPrefix\'',
      ],
      // Sometimes, table names are following the pattern database.schema.table.
      'Fully qualified - too many parts: 4' => [
        'identifier' => 'Chowra.Teressa.Bompuka.Katchal',
        'prefix' => '',
        'expectedCanonical' => '',
        'expectedMachine' => '',
        'expectedException' => 'The table identifier \'Chowra.Teressa.Bompuka.Katchal\' does not comply with the syntax [database.][schema.]table',
      ],
      'Fully qualified - too many parts: MySql not supporting \'database\'' => [
        'identifier' => 'Chowra.Teressa.Bompuka',
        'prefix' => '',
        'expectedCanonical' => '',
        'expectedMachine' => '',
        'expectedException' => 'MySql does not support the syntax [database.][schema.]table for the table identifier \'Chowra.Teressa.Bompuka\'. Avoid specifying the \'database\' part',
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
        'identifier' => 'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar.Trinket',
        'prefix' => '',
        'expectedCanonical' => '',
        'expectedMachine' => '',
        'expectedException' => 'The length of the schema identifier \'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar\' once canonicalized to \'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar\' is invalid (maximum allowed: 64)',
      ],
      'Fully qualified - invalid table name length' => [
        'identifier' => 'Camorta.VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'prefix' => '',
        'expectedCanonical' => 'Camorta.VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar',
        'expectedMachine' => '',
        'expectedException' => 'Table identifier \'VeryVeryVeryVeryHungryHungryHungryHungryHungryHungryHungryCaterpillar\' exceeds maximum allowed length (64)',
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
