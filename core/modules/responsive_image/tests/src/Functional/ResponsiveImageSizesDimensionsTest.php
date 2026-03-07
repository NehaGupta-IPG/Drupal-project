<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_image\Functional;

use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageStyleInterface;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\Tests\image\Functional\ImageFieldTestBase;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that sizes-type breakpoint mappings use the largest width descriptor's
 * transformed dimensions for width/height attributes on the <source> tag.
 *
 * @see \Drupal\responsive_image\ResponsiveImageBuilder::buildSourceAttributes()
 */
#[Group('responsive_image')]
#[RunTestsInSeparateProcesses]
class ResponsiveImageSizesDimensionsTest extends ImageFieldTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'responsive_image',
    'responsive_image_test_module',
  ];

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The responsive image style under test.
   *
   * @var \Drupal\responsive_image\Entity\ResponsiveImageStyle
   */
  protected $responsiveImgStyle;

  /**
   * Image style producing 240×160 px output (the "small" descriptor).
   *
   * @var \Drupal\image\ImageStyleInterface
   */
  protected ImageStyleInterface $smallStyle;

  /**
   * Image style producing 480×320 px output (the "large" descriptor).
   *
   * @var \Drupal\image\ImageStyleInterface
   */
  protected ImageStyleInterface $largeStyle;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fileUrlGenerator = $this->container->get('file_url_generator');

    $this->adminUser = $this->drupalCreateUser([
      'administer responsive images',
      'access content',
      'access administration pages',
      'administer site configuration',
      'administer content types',
      'administer node display',
      'administer nodes',
      'create article content',
      'edit any article content',
      'delete any article content',
      'administer image styles',
    ]);
    $this->drupalLogin($this->adminUser);

    // Create two image styles with different, known dimensions and a 3:2 ratio.
    $small = ImageStyle::create(['name' => 'test_small', 'label' => 'Test Small']);
    assert($small instanceof ImageStyleInterface);
    $small->addImageEffect([
      'id' => 'image_resize',
      'weight' => 0,
      'data' => ['width' => '240', 'height' => '160'],
    ]);
    $small->save();
    $this->smallStyle = $small;

    $large = ImageStyle::create(['name' => 'test_large', 'label' => 'Test Large']);
    assert($large instanceof ImageStyleInterface);
    $large->addImageEffect([
      'id' => 'image_resize',
      'weight' => 0,
      'data' => ['width' => '480', 'height' => '320'],
    ]);
    $large->save();
    $this->largeStyle = $large;

    $this->responsiveImgStyle = ResponsiveImageStyle::create([
      'id' => 'style_sizes_test',
      'label' => 'Style Sizes Test',
      'breakpoint_group' => 'responsive_image_test_module',
      'fallback_image_style' => 'test_large',
    ]);
  }

  /**
   * Tests that the source tag uses the largest width descriptor's dimensions.
   *
   * When sizes_image_styles lists small before large, the source tag must carry
   * the dimensions of the 480w descriptor (test_large), not the 240w one.
   */
  public function testSizesMappingUsesLargestWidthDescriptor(): void {
    $this->responsiveImgStyle
      ->addImageStyleMapping('responsive_image_test_module.narrow', '1x', [
        'image_mapping_type' => 'sizes',
        'image_mapping' => [
          'sizes' => '(min-width: 700px) 700px, 100vw',
          // Small listed before large: 240w processed first, 480w second.
          'sizes_image_styles' => ['test_small', 'test_large'],
        ],
      ])
      ->save();

    [$nid, $image_uri] = $this->createTestNode();
    $this->drupalGet('node/' . $nid);

    $small_url = $this->fileUrlGenerator->transformRelative($this->smallStyle->buildUrl($image_uri));
    $large_url = $this->fileUrlGenerator->transformRelative($this->largeStyle->buildUrl($image_uri));

    // Srcset entries must be sorted from smallest to largest width descriptor.
    $this->assertSession()->responseContains($small_url . ' 240w, ' . $large_url . ' 480w');

    // The source tag carrying the sizes attribute must expose the dimensions of
    // the largest width descriptor (480×320), not the first processed (240×160).
    $this->assertSession()->responseMatches(
      '/<source\s[^>]*sizes="[^"]*"[^>]*width="480"[^>]*height="320"[^>]*\/>/'
    );
    $this->assertSession()->responseNotMatches(
      '/<source\s[^>]*sizes="[^"]*"[^>]*width="240"[^>]*height="160"[^>]*\/>/'
    );
  }

  /**
   * Tests that dimension selection is by width value, not processing order.
   *
   * When sizes_image_styles lists the larger style first (480w then 240w), the
   * source tag must still use 480×320 — not 240×160 from the last processed
   * style. This is the primary regression test for the patch: without it, the
   * last-processed dimensions would "win" and produce incorrect attributes.
   */
  public function testSizesMappingDimensionsIgnoreStyleListOrder(): void {
    $this->responsiveImgStyle
      ->addImageStyleMapping('responsive_image_test_module.narrow', '1x', [
        'image_mapping_type' => 'sizes',
        'image_mapping' => [
          'sizes' => '(min-width: 700px) 700px, 100vw',
          // Large listed before small: without the patch, 240w would be last
          // processed and its dimensions (240×160) would be used incorrectly.
          'sizes_image_styles' => ['test_large', 'test_small'],
        ],
      ])
      ->save();

    [$nid, $image_uri] = $this->createTestNode();
    $this->drupalGet('node/' . $nid);

    // Srcset must still be sorted small-to-large regardless of style order.
    $small_url = $this->fileUrlGenerator->transformRelative($this->smallStyle->buildUrl($image_uri));
    $large_url = $this->fileUrlGenerator->transformRelative($this->largeStyle->buildUrl($image_uri));
    $this->assertSession()->responseContains($small_url . ' 240w, ' . $large_url . ' 480w');

    // Dimensions must still come from 480w (the largest), even though
    // test_large was processed first and test_small was processed last.
    $this->assertSession()->responseMatches(
      '/<source\s[^>]*sizes="[^"]*"[^>]*width="480"[^>]*height="320"[^>]*\/>/'
    );
    $this->assertSession()->responseNotMatches(
      '/<source\s[^>]*sizes="[^"]*"[^>]*width="240"[^>]*height="160"[^>]*\/>/'
    );
  }

  /**
   * Tests that a single-style sizes mapping still produces correct dimensions.
   *
   * When there is only one style in sizes_image_styles, the sizes_dimensions_map
   * has exactly one entry, and that entry's dimensions should be used.
   */
  public function testSizesMappingWithSingleStyle(): void {
    $this->responsiveImgStyle
      ->addImageStyleMapping('responsive_image_test_module.narrow', '1x', [
        'image_mapping_type' => 'sizes',
        'image_mapping' => [
          'sizes' => '(min-width: 700px) 700px, 100vw',
          'sizes_image_styles' => ['test_small'],
        ],
      ])
      ->save();

    [$nid, $image_uri] = $this->createTestNode();
    $this->drupalGet('node/' . $nid);

    $small_url = $this->fileUrlGenerator->transformRelative($this->smallStyle->buildUrl($image_uri));
    $this->assertSession()->responseContains($small_url . ' 240w');

    // With only one style, the single descriptor's dimensions must be used.
    $this->assertSession()->responseMatches(
      '/<source\s[^>]*sizes="[^"]*"[^>]*width="240"[^>]*height="160"[^>]*\/>/'
    );
  }

  /**
   * Tests sizes and image_style breakpoints each produce their own dimensions.
   *
   * A sizes-type narrow breakpoint (480w largest) and a plain image_style wide
   * breakpoint (800×400) must each carry their own correct width/height.
   */
  public function testSizesMappingAlongsideImageStyleBreakpoint(): void {
    $wide = ImageStyle::create(['name' => 'test_wide', 'label' => 'Test Wide']);
    assert($wide instanceof ImageStyleInterface);
    $wide->addImageEffect([
      'id' => 'image_resize',
      'weight' => 0,
      'data' => ['width' => '800', 'height' => '400'],
    ]);
    $wide->save();

    $this->responsiveImgStyle
      ->addImageStyleMapping('responsive_image_test_module.narrow', '1x', [
        'image_mapping_type' => 'sizes',
        'image_mapping' => [
          'sizes' => '(min-width: 700px) 700px, 100vw',
          'sizes_image_styles' => ['test_small', 'test_large'],
        ],
      ])
      ->addImageStyleMapping('responsive_image_test_module.wide', '1x', [
        'image_mapping_type' => 'image_style',
        'image_mapping' => 'test_wide',
      ])
      ->save();

    [$nid, $image_uri] = $this->createTestNode();
    $this->drupalGet('node/' . $nid);

    // Narrow (sizes): must use the 480w descriptor's dimensions.
    $this->assertSession()->responseMatches(
      '/<source\s[^>]*media="\(min-width: 560px\)"[^>]*sizes="[^"]*"[^>]*width="480"[^>]*height="320"[^>]*\/>/'
    );
    // Wide (image_style): must use test_wide's own dimensions.
    $this->assertSession()->responseMatches(
      '/<source\s[^>]*media="\(min-width: 851px\)"[^>]*width="800"[^>]*height="400"[^>]*\/>/'
    );
  }

  /**
   * Creates a test node with an image field, configures the display formatter,
   * and returns the node ID plus the uploaded image's file URI.
   *
   * @return array{0: int, 1: string}
   *   Tuple of [node ID, image file URI].
   */
  private function createTestNode(): array {
    $field_name = $this->randomMachineName();
    $this->createImageField($field_name, 'node', 'article', ['uri_scheme' => 'public']);

    // Use a large test image so resize effects always have room to work.
    $test_image = current($this->getTestFiles('image', 39325));
    $nid = $this->uploadNodeImage($test_image, $field_name, 'article', $this->randomMachineName());

    \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', 'article')
      ->setComponent($field_name, [
        'type' => 'responsive_image',
        'settings' => [
          'image_link' => '',
          'responsive_image_style' => 'style_sizes_test',
        ],
      ])
      ->save();

    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->load($nid);
    $image_uri = File::load($node->{$field_name}->target_id)->getFileUri();

    return [$nid, $image_uri];
  }

}
