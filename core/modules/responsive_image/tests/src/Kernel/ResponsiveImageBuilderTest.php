<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_image\Kernel;

use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\responsive_image\ResponsiveImageBuilder;
use Drupal\responsive_image\ResponsiveImageStyleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Responsive Image Builder service.
 */
#[Group('responsive_image')]
#[RunTestsInSeparateProcesses]
class ResponsiveImageBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'breakpoint',
    'responsive_image',
    'image',
    'file',
    'system',
    'user',
  ];

  /**
   * The responsive image builder service.
   *
   * @var \Drupal\responsive_image\ResponsiveImageBuilder
   */
  protected $responsiveImageBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'image']);
    $this->responsiveImageBuilder = $this->container->get(ResponsiveImageBuilder::class);
  }

  /**
   * Tests getImageStyleUrl() with an empty image style.
   */
  public function testGetImageStyleUrlEmptyImage(): void {
    $url = $this->responsiveImageBuilder->getImageStyleUrl(
      ResponsiveImageStyleInterface::EMPTY_IMAGE,
      'public://test.png'
    );
    // Should return the data URI for a 1px transparent GIF.
    $this->assertStringStartsWith('data:image/gif;base64,', $url);
  }

  /**
   * Tests getImageStyleUrl() with a supported image type.
   */
  public function testGetImageStyleUrlSupportedImage(): void {
    // Create an image style.
    $style = ImageStyle::create([
      'name' => 'test_style',
      'label' => 'Test Style',
    ]);
    $style->save();

    $url = $this->responsiveImageBuilder->getImageStyleUrl(
      'test_style',
      'public://test.png'
    );

    // Should return the image style derivative URL for supported types.
    $this->assertStringContainsString('styles/test_style', $url);
  }

  /**
   * Tests getImageStyleUrl() with an unsupported image type (SVG).
   */
  public function testGetImageStyleUrlUnsupportedImage(): void {
    // Create an image style.
    $style = ImageStyle::create([
      'name' => 'test_style',
      'label' => 'Test Style',
    ]);
    $style->save();

    $url = $this->responsiveImageBuilder->getImageStyleUrl(
      'test_style',
      'public://test.svg'
    );

    // Should return the original file URL for unsupported types (SVG).
    $this->assertStringNotContainsString('styles/test_style', $url);
    $this->assertStringContainsString('test.svg', $url);
  }

  /**
   * Tests getImageStyleUrl() with a non-existent image style.
   */
  public function testGetImageStyleUrlNonExistentStyle(): void {
    $url = $this->responsiveImageBuilder->getImageStyleUrl(
      'non_existent_style',
      'public://test.png'
    );

    // Should return the original file URL when style doesn't exist.
    $this->assertStringContainsString('test.png', $url);
    $this->assertStringNotContainsString('styles/', $url);
  }

}
