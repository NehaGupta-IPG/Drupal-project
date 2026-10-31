<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Kernel\Plugin\Action;

use Drupal\Core\Image\ImageFactory;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Entity\Action;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests File Image Styles actions.
 *
 * @covers \Drupal\image\Plugin\Action\FileImageStylesGenerateAction
 * @covers \Drupal\image\Plugin\Action\FileOriginalImageStyleAction
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
   * An image file path for uploading.
   */
  protected FileInterface $image;

  /**
   * An image style.
   */
  protected ImageStyle $imageStyle;

  /**
   * The ID of the resize effect.
   */
  protected string $resizeEffectId;

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
    $this->installEntitySchema('image_style');

    $this->imageFactory = $this->container->get('image.factory');

    $this->imageStyle = ImageStyle::create([
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
    $this->imageStyle->addImageEffect($convert_effect);
    $resize_effect = [
      'id' => 'image_scale_and_crop',
      'data' => [
        'anchor' => 'center-center',
        'width' => 1,
        'height' => 1,
      ],
      'weight' => 1,
    ];
    $this->resizeEffectId = $this->imageStyle->addImageEffect($resize_effect);
    $this->imageStyle->save();

    $image_files = $this->drupalGetTestFiles('image');
    $this->image = File::create((array) current($image_files));
    $this->image->save();
  }

  /**
   * Test File Image Styles Generate Action.
   */
  public function testFileImageStylesGenerateAction(): void {
    // Make sure the derivative does not exist, initially.
    $derivative_uri = $this->imageStyle->buildUri($this->image->getFileUri());
    $this->assertFalse(file_exists($derivative_uri));

    // Create the File Image Styles Generate Action.
    $action = Action::create([
      'id' => 'file_image_styles_generate_action',
      'label' => 'Optimize image',
      'plugin' => 'file_image_styles_generate_action',
      'configuration' => [
        'image_styles' => ['original_style', 'invalid_style'],
      ],
    ]);
    $action->save();
    $action->execute([$this->image]);
    // Make sure the derivative images was generated and the image style effects
    // were applied.
    $this->assertTrue(file_exists($derivative_uri));
    $derivative_image = $this->imageFactory->get($derivative_uri);
    $this->assertEquals('image/webp', $derivative_image->getMimeType());
    $this->assertEquals($derivative_image->getHeight(), 1);

    // Update the image style effects.
    $resize_effect = $this->imageStyle->getEffect($this->resizeEffectId);
    $this->imageStyle->deleteImageEffect($resize_effect);
    $resize_effect_config = $resize_effect->getConfiguration();
    $resize_effect_config['data']['width'] = 2;
    $resize_effect_config['data']['height'] = 2;
    $this->imageStyle->addImageEffect($resize_effect_config);
    $this->imageStyle->save();

    // Regenerate the derivative image.
    $action->set('configuration', [
      'image_styles' => ['original_style'],
      'regenerate' => TRUE,
    ]);
    $action->save();
    $action->execute([$this->image]);
    $derivative_image = $this->imageFactory->get($derivative_uri);
    $this->assertEquals($derivative_image->getHeight(), 2);
  }

  /**
   * Test File Original Image Style Action.
   */
  public function testFileImageStyleAction(): void {
    $original_image = $this->imageFactory->get($this->image->getFileUri());

    // Create an action with a non existing image style.
    $action = Action::create([
      'id' => 'file_original_image_style_action',
      'label' => 'Optimize image',
      'plugin' => 'file_original_image_style_action',
      'configuration' => [
        'image_style' => 'invalid_style',
      ],
    ]);
    $action->save();

    // Check that the action does not execute.
    $action->execute([$this->image]);
    $file_not_styled = File::load($this->image->id());
    $not_styled_image = $this->imageFactory->get($file_not_styled->getFileUri());
    $this->assertEquals($original_image->getFileSize(), $not_styled_image->getFileSize());
    $this->assertEquals($original_image->getMimeType(), $not_styled_image->getMimeType());
    $this->assertEquals($original_image->getHeight(), $not_styled_image->getHeight());

    // Correct action image style configuration.
    $action->set('configuration', ['image_style' => 'original_style']);
    $action->save();
    $action->execute([$this->image]);

    // Test that the original file has been replaced with the styled one.
    $file_styled = File::load($this->image->id());
    $styled_image = $this->imageFactory->get($file_styled->getFileUri());
    $this->assertNotEquals($original_image->getFileSize(), $styled_image->getFileSize());
    $this->assertNotEquals($original_image->getMimeType(), $styled_image->getMimeType());
    $this->assertNotEquals($original_image->getHeight(), $styled_image->getHeight());
    $this->assertFalse(file_exists($original_image->getSource()));
    $this->assertTrue(file_exists($styled_image->getSource()));
    $this->assertEquals($styled_image->getHeight(), 1);
  }

}
