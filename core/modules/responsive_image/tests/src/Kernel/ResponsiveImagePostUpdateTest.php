<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_image\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests responsive_image post update functions.
 */
#[Group('responsive_image')]
#[RunTestsInSeparateProcesses]
class ResponsiveImagePostUpdateTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'breakpoint',
    'file',
    'image',
    'responsive_image',
  ];

  /**
   * Tests responsive_image_post_update_add_fetchpriority_setting().
   */
  public function testAddFetchpriorityDefaultSetting(): void {
    $this->installConfig(['field']);
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    $field_name = $this->randomMachineName();
    ResponsiveImageStyle::create(['id' => 'foo', 'label' => 'Foo'])->save();

    FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => $field_name,
      'type' => 'image',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => $field_name,
      'bundle' => 'entity_test',
    ])->save();

    // Simulate a pre-update site: a responsive image formatter without the
    // 'fetchpriority' key in its 'image_loading' settings.
    EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ])->setComponent($field_name, [
      'type' => 'responsive_image',
      'settings' => [
        'responsive_image_style' => 'foo',
        'image_link' => '',
        'image_loading' => [
          'attribute' => 'lazy',
        ],
      ],
    ])->save();

    $component = EntityViewDisplay::load('entity_test.entity_test.default')->getComponent($field_name);
    $this->assertArrayNotHasKey('fetchpriority', $component['settings']['image_loading']);

    \Drupal::moduleHandler()->loadInclude('responsive_image', 'post_update.php');
    $sandbox = [];
    responsive_image_post_update_add_fetchpriority_setting($sandbox);

    $component = EntityViewDisplay::load('entity_test.entity_test.default')->getComponent($field_name);
    $this->assertArrayHasKey('fetchpriority', $component['settings']['image_loading']);
    $this->assertNull($component['settings']['image_loading']['fetchpriority']);
  }

}
