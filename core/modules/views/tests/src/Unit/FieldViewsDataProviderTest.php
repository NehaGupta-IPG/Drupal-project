<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\FieldViewsDataProvider;

/**
 * @coversDefaultClass \Drupal\views\FieldViewsDataProvider
 * @group views
 */
class FieldViewsDataProviderTest extends UnitTestCase {

  /**
   * @covers ::getHandlers
   * @dataProvider providerGetHandlers
   */
  public function testGetHandlers(string $column_type, string $field_type, array $expected): void {
    $entity_type_manager = $this->createMock(EntityTypeManager::class);
    $field_type_plugin_manager = $this->createMock(FieldTypePluginManagerInterface::class);
    $entity_field_manager = $this->createMock(EntityFieldManagerInterface::class);

    $provider = new FieldViewsDataProvider($entity_type_manager, $field_type_plugin_manager, $entity_field_manager);
    $this->assertSame($expected, $provider->getHandlers($column_type, $field_type));
  }

  public static function providerGetHandlers(): array {
    return [
      'int' => [
        'int',
        'some_field',
        ['numeric', 'numeric', 'standard'],
      ],
      'mediumint' => [
        'mediumint',
        'some_field',
        ['numeric', 'numeric', 'standard'],
      ],
      'tinyint' => [
        'tinyint',
        'some_field',
        ['numeric', 'numeric', 'standard'],
      ],
      'bigint' => [
        'bigint',
        'some_field',
        ['numeric', 'numeric', 'standard'],
      ],
      'serial' => [
        'serial',
        'some_field',
        ['numeric', 'numeric', 'standard'],
      ],
      'numeric' => [
        'numeric',
        'some_field',
        ['numeric', 'numeric', 'standard'],
      ],
      'float' => [
        'float',
        'some_field',
        ['numeric', 'numeric', 'standard'],
      ],
      'boolean' => [
        'tinyint',
        'boolean',
        ['boolean', 'numeric', 'standard'],
      ],
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
