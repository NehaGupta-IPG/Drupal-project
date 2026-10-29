<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Plugin;

use Drupal\file\Entity\File;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests field entity label functionality.
 *
 * @group file
 */
class FieldEntityLabelTest extends BrowserTestBase {

  use ImageFieldCreationTrait;
  use TestFileCreationTrait {
    getTestFiles as drupalGetTestFiles;
  }

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['views', 'file', 'image', 'node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * An authenticated user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'access files overview',
      'bypass node access',
      'delete any file',
    ]);

    $this->drupalLogin($this->adminUser);
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
  }

  /**
   * Test file usage view.
   */
  public function testFileUsageView(): void {
    // Create files to use as the default images.
    $files = $this->drupalGetTestFiles('image');

    $default_images = [];
    foreach (['field_storage', 'field_storage_new'] as $image_target) {
      $file = File::create((array) array_pop($files));
      $file->save();
      $default_images[$image_target] = $file;
    }

    // Create an image field storage and add a field to the article content
    // type.
    $field_name = $this->randomMachineName();
    $storage_settings['default_image'] = [
      'uuid' => $default_images['field_storage']->uuid(),
      'alt' => '',
      'title' => '',
      'width' => 0,
      'height' => 0,
    ];
    $field_settings['default_image'] = [
      'uuid' => $default_images['field_storage']->uuid(),
      'alt' => '',
      'title' => '',
      'width' => 0,
      'height' => 0,
    ];
    $widget_settings = [
      'preview_image_style' => 'medium',
    ];
    // Create a image file for article content type.
    $field = $this->createImageField($field_name, 'article', $storage_settings, $field_settings, $widget_settings);
    $field_storage = $field->getFieldStorageDefinition();

    // Upload a new default for the field storage.
    $default_image_settings = $field_storage->getSetting('default_image');
    $default_image_settings['uuid'] = $default_images['field_storage_new']->uuid();
    $field_storage->setSetting('default_image', $default_image_settings);
    $field_storage->save();

    // Confirm the default image file is used.
    $this->drupalGet('/admin/content/files/usage/' . $default_images['field_storage_new']->id());
    $this->assertSession()->statusCodeEquals(200);
    // Confirm the number of usage.
    $this->assertSession()->elementTextEquals('xpath', '//td[@headers="view-count-table-column"]', '1');
  }

}
