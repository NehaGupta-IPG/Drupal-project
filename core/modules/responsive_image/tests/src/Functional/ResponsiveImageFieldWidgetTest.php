<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_image\Functional;

use Drupal\Tests\image\Functional\ImageFieldTestBase;
use Drupal\responsive_image\Plugin\Field\FieldWidget\ResponsiveImageWidget;

/**
 * Tests the responsive image field widget.
 *
 * @group image
 */
class ResponsiveImageFieldWidgetTest extends ImageFieldTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'responsive_image',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests responsive image widget element.
   */
  public function testWidgetElement() {
    // Check for responsive image widget in add/node/article page
    $field_name = mb_strtolower($this->randomMachineName());
    $this->createImageField($field_name, 'node', 'article');
    // Update form display to use responsive image widget.
    \Drupal::service('entity_display.repository')->getFormDisplay('node', 'article')
      ->setComponent($field_name, [
        'type' => 'responsive_image_image',
        'settings' => ResponsiveImageWidget::defaultSettings(),
      ])
      ->save();

    $this->drupalGet('node/add/article');
    // Verify that the responsive image field widget is found on node/add page.
    $this->assertSession()->elementExists('xpath', '//div[contains(@class, "field--widget-responsive-image-image")]');
  }

}
