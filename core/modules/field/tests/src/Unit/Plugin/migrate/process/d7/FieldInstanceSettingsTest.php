<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Unit\Plugin\migrate\process\d7;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Plugin\migrate\process\d7\FieldInstanceSettings;
use Drupal\migrate\MigrateLookup;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\Tests\migrate\Unit\MigrateTestCase;

/**
 * @coversDefaultClass \Drupal\field\Plugin\migrate\process\d7\FieldInstanceSettings
 * @group field
 */
class FieldInstanceSettingsTest extends MigrateTestCase {

  /**
   * Tests transformation of image field settings.
   *
   * @covers ::transform
   *
   * @dataProvider providerTestTransformImageSettings
   */
  public function testTransformImageSettings($type, $instance_settings, $widget_settings, $field_definition, $lookup_result, $expected_results): void {
    $migrate_lookup = $this->createMock(MigrateLookup::class);
    $migrate_lookup->method('lookup')->willReturn($lookup_result);

    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage->expects($this->once())
      ->method('loadMultiple')
      ->willReturn([]);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager
      ->expects($this->any())
      ->method('getStorage')
      ->with('node_type')
      ->willReturn($entity_storage);
    $entity_type_manager->expects($this->any())->method('getStorage')->willReturn($entity_storage);

    $plugin = new FieldInstanceSettings([], 'd7_field_instance_settings', [], $migrate_lookup, $entity_type_manager);

    $executable = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->getMockBuilder(Row::class)
      ->disableOriginalConstructor()
      ->getMock();
    $row->method('getSourceProperty')->willReturn($type);

    $value = [$instance_settings, $widget_settings, $field_definition];
    $value = $plugin->transform($value, $executable, $row, 'foo');
    $this->assertSame($expected_results, $value);
  }

  /**
   * Provides data to testTransformImageSettings().
   */
  public static function providerTestTransformImageSettings(): array {
    $data['settings']['referenceable_types'] = [
      'article' => 0,
      'page' => 0,
      'blog' => 0,
      'book' => 0,
      'et' => 0,
      'forum' => 0,
      'test_content_type' => 0,
      'a_thirty_two_character_type_name' => 0,
    ];

    return [
      'image' => [
        'image_image',
        [],
        ['type' => 'image_image'],
        ['data' => ''],
        [],
        [
          'default_image' => [
            'alt' => '',
            'title' => '',
            'width' => NULL,
            'height' => NULL,
            'uuid' => '',
          ],
        ],
      ],
      'node_reference one content type' => [
        'node_reference',
        [],
        ['type' => 'node_reference'],
        [
          'data' => serialize([
            'settings' => [
              'referenceable_types' => [
                'page' => 'page',
              ],
            ],
          ]),
        ],
        [['type' => 'new_page']],
        [
          'handler' => 'default:node',
          'handler_settings' => [
            'sort' => [
              'field' => '_none',
              'direction' => 'ASC',
            ],
            'target_bundles' => [
              'new_page' => 'new_page',
            ],
          ],
        ],
      ],
      'node_reference no content types' => [
        'node_reference',
        [],
        ['type' => 'node_reference'],
        ['data' => serialize($data)],
        [],
        [
          'handler' => 'default:node',
          'handler_settings' => [
            'sort' => [
              'field' => '_none',
              'direction' => 'ASC',
            ],
            'target_bundles' => NULL,
          ],
        ],
      ],
    ];
  }

}
