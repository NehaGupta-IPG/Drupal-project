<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Kernel\Plugin\Action;

use Drupal\Core\Image\ImageFactory;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Entity\Action;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests Content Entity Translate action.
 *
 * @covers \Drupal\image\Plugin\Action\FileImageStyleAction
 *
 * @group action
 * @group image
 */
class FileImageStyleActionTest extends KernelTestBase {

  use TestFileCreationTrait {
    getTestFiles as drupalGetTestFiles;
  }

  /**
   * The image factory service.
   */
  protected ImageFactory $imageFactory;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'image',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->installSchema('file', ['file_usage']);

    $this->imageFactory = $this->container->get('image.factory');

    $original_style = ImageStyle::create([
      'name' => 'original_style',
      'label' => 'Original style',
    ]);

    // Add an image effect.
    $convert_effect = [
      'id' => 'image_convert',
      'data' => [
        'extension' => 'webp',
      ],
      'weight' => 0,
    ];
    $original_style->addImageEffect($convert_effect);
    $resize_effect = [
      'id' => 'image_scale_and_crop',
      'data' => [
        'anchor' => 'center-center',
        'width' => 1,
        'height' => 1,
      ],
      'weight' => 1,
    ];
    $original_style->addImageEffect($resize_effect);
    $original_style->save();
  }

  /**
   * Tests moving a randomly generated image.
   */
  public function testFileImageStyleAction(): void {
    // Create a file for testing.
    $file_original = File::create((array) current($this->drupalGetTestFiles('image')));
    $file_original->save();
    $original_image = $this->imageFactory->get($file_original->getFileUri());

    // Create an action with unexisting image style.
    $action = Action::create([
      'id' => 'file_image_style_action',
      'label' => 'Optimize image',
      'plugin' => 'file_image_style_action',
      'configuration' => [
        'image_style' => 'invalid_style',
      ],
    ]);
    $action->save();

    // Pick a file for testing.
    $action->execute([$file_original]);
    $file_not_styled = File::load($file_original->id());
    $not_styled_image = $this->imageFactory->get($file_not_styled->getFileUri());
    $this->assertEquals($original_image->getFileSize(), $not_styled_image->getFileSize());
    $this->assertEquals($original_image->getMimeType(), $not_styled_image->getMimeType());
    $this->assertEquals($original_image->getHeight(), $not_styled_image->getHeight());

    // Correct action image style configuration.
    $action->set('configuration', ['image_style' => 'original_style']);
    $action->save();

    $action->execute([$file_original]);

    // Test that the original file has been replaced with the styled one.
    $file_styled = File::load($file_original->id());
    $styled_image = $this->imageFactory->get($file_styled->getFileUri());
    $this->assertNotEquals($original_image->getFileSize(), $styled_image->getFileSize());
    $this->assertNotEquals($original_image->getMimeType(), $styled_image->getMimeType());
    $this->assertNotEquals($original_image->getHeight(), $styled_image->getHeight());
    $this->assertFalse(file_exists($original_image->getSource()));
    $this->assertTrue(file_exists($styled_image->getSource()));
  }

}
