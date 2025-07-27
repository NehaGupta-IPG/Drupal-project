<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\FieldViewsDataProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests Drupal\views\FieldViewsDataProvider.
 */
#[CoversClass(FieldViewsDataProvider::class)]
#[Group('views')]
class FieldViewsDataProviderTest extends UnitTestCase {

  /**
   * Tests get handlers.
   *
   * @param string $column_type
   *   The type of data stored in the field's database table column.
   * @param string $field_type.
   *   The ID of the field's type plugin.
   * @param array $expected.
   *   The expected output of getHandlers().
   *
   * @legacy-covers ::getHandlers
   */
  #[DataProvider('providerGetHandlers')]
  public function testGetHandlers(string $column_type, string $field_type, array $expected): void {
    $entity_type_manager = $this->createMock(EntityTypeManager::class);
    $field_type_plugin_manager = $this->createMock(FieldTypePluginManagerInterface::class);
    $entity_field_manager = $this->createMock(EntityFieldManagerInterface::class);

    $provider = new FieldViewsDataProvider($entity_type_manager, $field_type_plugin_manager, $entity_field_manager);
    $this->assertSame($expected, $provider->getHandlers($column_type, $field_type));
  }

  /**
   * Provides test data for testGetHandlers().
   *
   * @return array[]
   *   Test cases. See testGetHandlers() for key documentation.
   */
  public static function providerGetHandlers(): array {
    return [
      // Tests fields with int columns.
      'int' => [
        'int',
        'some_field',
        ['numeric', 'numeric', 'standard'],
      ],
      // Tests fields with mediumint columns.
      'mediumint' => [
        'mediumint',
        'some_field',
        ['numeric', 'numeric', 'standard'],
      ],
      // Tests fields with tinyint columns.
      'tinyint' => [
        'tinyint',
        'some_field',
        ['numeric', 'numeric', 'standard'],
      ],
      // Tests fields with bigint columns.
      'bigint' => [
        'bigint',
        'some_field',
        ['numeric', 'numeric', 'standard'],
      ],
      // Tests fields with serial columns.
      'serial' => [
        'serial',
        'some_field',
        ['numeric', 'numeric', 'standard'],
      ],
      // Tests fields with numeric columns.
      'numeric' => [
        'numeric',
        'some_field',
        ['numeric', 'numeric', 'standard'],
      ],
      // Tests fields with float columns.
      'float' => [
        'float',
        'some_field',
        ['numeric', 'numeric', 'standard'],
      ],
      // Tests boolean fields with tinyint columns.
      'boolean' => [
        'tinyint',
        'boolean',
        ['boolean', 'numeric', 'standard'],
      ],
      // Tests timestamp fields with int columns.
      'timestamp' => [
        'int',
        'timestamp',
        ['date', 'numeric', 'standard'],
      ],
      'default' => [
        'some_column',
        'some_field',
        ['string', 'string', 'standard'],
      ],
    ];
  }

}
