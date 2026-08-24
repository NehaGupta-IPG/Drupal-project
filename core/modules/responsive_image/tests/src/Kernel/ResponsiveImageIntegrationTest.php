<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_image\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the integration of responsive image with other components.
 */
#[Group('responsive_image')]
#[RunTestsInSeparateProcesses]
class ResponsiveImageIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'responsive_image',
    'field',
    'image',
    'file',
    'entity_test',
    'breakpoint',
    'responsive_image_test_module',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
  }

  /**
   * Tests integration with entity view display.
   */
  public function testEntityViewDisplayDependency(): void {
    // Create a responsive image style.
    ResponsiveImageStyle::create([
      'id' => 'foo',
      'label' => 'Foo',
      'breakpoint_group' => 'responsive_image_test_module',
    ])->save();
    // Create an image field to be used with a responsive image formatter.
    FieldStorageConfig::create([
      'type' => 'image',
      'entity_type' => 'entity_test',
      'field_name' => 'bar',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'bar',
    ])->save();
    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ]);
    $display->setComponent('bar', [
      'type' => 'responsive_image',
      'label' => 'hidden',
      'settings' => ['responsive_image_style' => 'foo', 'image_link' => ''],
      'third_party_settings' => [],
    ])->save();

    // Check that the 'foo' field is on the display.
    $this->assertNotNull($display = EntityViewDisplay::load('entity_test.entity_test.default'));
    $this->assertNotEmpty($display->getComponent('bar'));
    $this->assertArrayNotHasKey('bar', $display->get('hidden'));

    // Delete the responsive image style.
    ResponsiveImageStyle::load('foo')->delete();

    // Check that the view display was not deleted.
    $this->assertNotNull($display = EntityViewDisplay::load('entity_test.entity_test.default'));
    // Check that the 'foo' field was disabled.
    $this->assertNull($display->getComponent('bar'));
    $this->assertArrayHasKey('bar', $display->get('hidden'));
  }

  /**
   * Tests the preload warning element in the formatter settings form.
   *
   * Verifies two scenarios:
   * - Breakpoints with non-exclusive media queries (min-width only) trigger the
   *   warning render element.
   * - When all breakpoints have empty media queries, non-exclusive conditions
   *   embedded in sizes attribute values are used as a fallback and also trigger
   *   the warning.
   */
  public function testPreloadWarningForNonExclusiveBreakpoints(): void {
    // Create an image field so the formatter plugin has a valid field definition.
    FieldStorageConfig::create([
      'type' => 'image',
      'entity_type' => 'entity_test',
      'field_name' => 'test_img',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'test_img',
      'label' => 'Test image',
    ])->save();
    $field_definition = FieldConfig::loadByName('entity_test', 'entity_test', 'test_img');

    // --- Scenario A: non-exclusive breakpoint media queries ---
    // mobile=(min-width:0px) and wide=(min-width:851px) are both min-width only.
    $style = ResponsiveImageStyle::create([
      'id' => 'test_preload',
      'label' => 'Test preload',
      'breakpoint_group' => 'responsive_image_test_module',
      'fallback_image_style' => 'large',
    ]);
    $style
      ->addImageStyleMapping('responsive_image_test_module.mobile', '1x', [
        'image_mapping_type' => 'image_style',
        'image_mapping' => 'thumbnail',
      ])
      ->addImageStyleMapping('responsive_image_test_module.wide', '1x', [
        'image_mapping_type' => 'image_style',
        'image_mapping' => 'large',
      ])
      ->save();

    $formatter = $this->container->get('plugin.manager.field.formatter')
      ->createInstance('responsive_image', [
        'field_definition' => $field_definition,
        'settings' => [
          'responsive_image_style' => 'test_preload',
          'image_link' => '',
          'image_loading' => ['preload' => TRUE, 'attribute' => 'lazy'],
        ],
        'label' => 'hidden',
        'view_mode' => 'default',
        'third_party_settings' => [],
      ]);

    $form = $formatter->settingsForm([], new FormState());

    $this->assertArrayHasKey('preload_non_exclusive_warning', $form['image_loading'],
      'Warning element is rendered when at least one available style has non-exclusive breakpoints.');
    $warning_markup = (string) $form['image_loading']['preload_non_exclusive_warning']['message']['#markup'];
    $this->assertStringContainsString('not mutually exclusive', $warning_markup);
    // The #states should list the non-exclusive style ID so the warning appears
    // only when that style is selected alongside the preload checkbox.
    $states_style_values = $form['image_loading']['preload_non_exclusive_warning']['#states']['visible'][':input[name*="responsive_image_style"]'];
    $this->assertContains(['value' => 'test_preload'], $states_style_values,
      'Non-exclusive style ID is present in the #states conditions.');

    // --- Scenario B: no breakpoint media queries, non-exclusive sizes conditions ---
    // The 'empty' breakpoint has mediaQuery:''. With no explicit media queries,
    // the formatter falls back to inspecting the sizes attribute values.
    // Two min-width-only conditions in the sizes string are non-exclusive.
    $style_sizes = ResponsiveImageStyle::create([
      'id' => 'test_preload_sizes',
      'label' => 'Test preload sizes',
      'breakpoint_group' => 'responsive_image_test_module',
      'fallback_image_style' => 'large',
    ]);
    $style_sizes->addImageStyleMapping('responsive_image_test_module.empty', '1x', [
      'image_mapping_type' => 'sizes',
      'image_mapping' => [
        'sizes' => '(min-width: 700px) 700px, (min-width: 400px) 400px, 100vw',
        'sizes_image_styles' => ['large', 'medium'],
      ],
    ])->save();

    $formatter_sizes = $this->container->get('plugin.manager.field.formatter')
      ->createInstance('responsive_image', [
        'field_definition' => $field_definition,
        'settings' => [
          'responsive_image_style' => 'test_preload_sizes',
          'image_link' => '',
          'image_loading' => ['preload' => TRUE, 'attribute' => 'lazy'],
        ],
        'label' => 'hidden',
        'view_mode' => 'default',
        'third_party_settings' => [],
      ]);

    $form_sizes = $formatter_sizes->settingsForm([], new FormState());

    $this->assertArrayHasKey('preload_non_exclusive_warning', $form_sizes['image_loading'],
      'Warning is present when sizes conditions are non-exclusive and breakpoints carry no media query.');
  }

}
