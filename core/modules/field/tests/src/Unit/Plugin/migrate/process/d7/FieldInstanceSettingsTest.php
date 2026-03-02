<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Unit\Plugin\migrate\process\d7;

use Drupal\field\Plugin\migrate\process\d7\FieldInstanceSettings;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\Tests\migrate\Unit\MigrateTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Tests Drupal\field\Plugin\migrate\process\d7\FieldInstanceSettings.
 */
#[CoversClass(FieldInstanceSettings::class)]
#[Group('field')]
#[IgnoreDeprecations]
class FieldInstanceSettingsTest extends MigrateTestCase {

  /**
   * Tests transformation of image field settings.
   */
  public function testTransformImageSettings(): void {
    $plugin = new FieldInstanceSettings([], 'd7_field_instance_settings', []);

    $executable = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->prophesize(Row::class);
    $row->getSourceProperty('type')->willReturn(NULL);

    $value = $plugin->transform([[], ['type' => 'image_image'], ['data' => '']], $executable, $row->reveal(), 'foo');
    $this->assertIsArray($value['default_image']);
    $this->assertSame('', $value['default_image']['alt']);
    $this->assertSame('', $value['default_image']['title']);
    $this->assertNull($value['default_image']['width']);
    $this->assertNull($value['default_image']['height']);
    $this->assertSame('', $value['default_image']['uuid']);

    $row->getSourceProperty('type')->willReturn('entityreference');
    $field_definition = ['data' => 'a:8:{s:12:"entity_types";a:0:{}s:17:"field_permissions";a:1:{s:4:"type";i:0;}s:7:"indexes";a:1:{s:9:"target_id";a:1:{i:0;s:9:"target_id";}}s:8:"settings";a:4:{s:7:"handler";s:5:"views";s:16:"handler_settings";a:2:{s:9:"behaviors";a:1:{s:17:"views-select-list";a:1:{s:6:"status";i:0;}}s:4:"view";a:3:{s:4:"args";a:2:{i:0;s:56:"[field_collection_item:host:node:field-research-area:id]";i:1;s:47:"[field_collection_item:host:node:field-year:id]";}s:12:"display_name";s:17:"entityreference_1";s:9:"view_name";s:24:"utility_er_courses_upper";}}s:16:"profile2_private";b:0;s:11:"target_type";s:8:"academic";}s:12:"translatable";i:0;s:7:"storage";a:5:{s:4:"type";s:17:"field_sql_storage";s:8:"settings";a:0:{}s:6:"module";s:17:"field_sql_storage";s:6:"active";s:1:"1";s:7:"details";a:1:{s:3:"sql";a:2:{s:18:"FIELD_LOAD_CURRENT";a:1:{s:37:"field_data_field_draft_course_section";a:1:{s:9:"target_id";s:36:"field_draft_course_section_target_id";}}s:19:"FIELD_LOAD_REVISION";a:1:{s:41:"field_revision_field_draft_course_section";a:1:{s:9:"target_id";s:36:"field_draft_course_section_target_id";}}}}}s:12:"foreign keys";a:1:{s:12:"eck_academic";a:2:{s:5:"table";s:12:"eck_academic";s:7:"columns";a:1:{s:9:"target_id";s:2:"id";}}}s:2:"id";s:2:"70";}'];
    $value = $plugin->transform([[], ['type' => 'entityreference_autocomplete'], $field_definition], $executable, $row->reveal(), 'foo');
    $this->assertIsArray($value['handler_settings']);
    $this->assertSame([
      'behaviors' => [
        'views-select-list' => [
          'status' => 0,
        ],
      ],
      'view' => [
        'args' => [
          0 => '[field_collection_item:host:node:field-research-area:id]',
          1 => '[field_collection_item:host:node:field-year:id]',
        ],
        'display_name' => 'entityreference_1',
        'view_name' => 'utility_er_courses_upper',
      ],
      'target_bundles' => NULL,
      'sort' => [
        'field' => '_none',
        'direction' => 'ASC',
      ],
    ], $value['handler_settings']);
  }

}
