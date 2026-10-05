<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Kernel;

use Drupal\image\Entity\ImageStyle;
use Drupal\image\Hook\ImageThemeHooks;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the preprocessing of image style previews.
 *
 * @see \Drupal\image\Hook\ImageThemeHooks::preprocessImageStylePreview()
 */
#[Group('image')]
#[RunTestsInSeparateProcesses]
class ImageStylePreviewTest extends KernelTestBase {

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
  protected bool $strictConfigSchema = FALSE;

  /**
   * The image style under test.
   */
  protected ImageStyle $style;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'image']);
    $this->installEntitySchema('file');

    $this->style = ImageStyle::create([
      'name' => 'preview_test',
      'label' => 'Preview test',
    ]);
    $this->style->addImageEffect([
      'id' => 'image_scale',
      'data' => ['width' => 100, 'upscale' => FALSE],
      'weight' => 0,
    ]);
    $this->style->save();
  }

  /**
   * Tests that an unreadable source image does not cause a division by zero.
   */
  public function testPreviewWithInvalidSourceImage(): void {
    $this->config('image.settings')
      ->set('preview_image', 'public://does-not-exist.png')
      ->save();

    $variables = $this->preprocessPreview();

    foreach (['original', 'derivative'] as $type) {
      $this->assertSame(160, $variables['preview'][$type]['width'], "The $type preview falls back to the sample width.");
      $this->assertSame(160, $variables['preview'][$type]['height'], "The $type preview falls back to the sample height.");
      $this->assertArrayHasKey('rendered', $variables[$type], "The $type preview is rendered.");
    }
  }

  /**
   * Tests that valid source images keep their aspect ratio in the preview.
   *
   * @param string $fixture
   *   The core test fixture to use as the preview image.
   * @param int $expected_width
   *   The expected original preview width.
   * @param int $expected_height
   *   The expected original preview height.
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('providerValidSourceImage')]
  public function testPreviewWithValidSourceImage(string $fixture, int $expected_width, int $expected_height): void {
    $uri = 'public://' . basename($fixture);
    \Drupal::service('file_system')->copy($this->root . '/' . $fixture, $uri);
    $this->config('image.settings')->set('preview_image', $uri)->save();

    $variables = $this->preprocessPreview();

    $this->assertEquals($expected_width, $variables['preview']['original']['width']);
    $this->assertEquals($expected_height, $variables['preview']['original']['height']);
    $this->assertLessThanOrEqual(160, $variables['preview']['derivative']['width']);
    $this->assertLessThanOrEqual(160, $variables['preview']['derivative']['height']);
    $this->assertGreaterThan(0, $variables['preview']['derivative']['width']);
    $this->assertGreaterThan(0, $variables['preview']['derivative']['height']);
  }

  /**
   * Data provider for ::testPreviewWithValidSourceImage().
   */
  public static function providerValidSourceImage(): array {
    return [
      // core/misc/druplicon.png is 88x100 (portrait).
      'portrait' => ['core/misc/druplicon.png', 88, 100],
      // core/modules/image/sample.png is 800x600 (landscape).
      'landscape' => ['core/modules/image/sample.png', 160, 120],
    ];
  }

  /**
   * Runs the preprocess hook for the test image style.
   *
   * @return array
   *   The preprocessed template variables.
   */
  protected function preprocessPreview(): array {
    $variables = ['style' => $this->style];
    \Drupal::service(ImageThemeHooks::class)->preprocessImageStylePreview($variables);
    return $variables;
  }

}
