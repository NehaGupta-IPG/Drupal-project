<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_image\Functional;

use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\Tests\image\Functional\ImageFieldTestBase;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests responsive image dimensions are from the selected image style.
 */
#[Group('responsive_image')]
#[RunTestsInSeparateProcesses]
class ResponsiveImageDimensionsTest extends ImageFieldTestBase {

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
   * Tests img tag uses selected image style dimensions, not fallback.
   *
   * When a responsive image style has only one source with no media query,
   * it outputs an img tag with srcset instead of a picture element. The img
   * tag should use the dimensions from the selected image style in the source,
   * not the fallback image style dimensions.
   */
  public function testImageTagUsesSelectedStyleDimensionsNotFallback(): void {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Create user.
    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'create article content',
    ]);
    $this->drupalLogin($this->adminUser);

    // Set up image styles with known dimensions.
    // Medium style: 220x220
    $medium_style = ImageStyle::load('medium');
    $medium_style->flush();
    $medium_style->addImageEffect([
      'id' => 'image_scale',
      'weight' => 0,
      'data' => [
        'width' => 220,
        'height' => 220,
        'upscale' => FALSE,
      ],
    ]);
    $medium_style->save();

    // Large style (fallback): 480x480
    $large_style = ImageStyle::load('large');
    $large_style->flush();
    $large_style->addImageEffect([
      'id' => 'image_scale',
      'weight' => 0,
      'data' => [
        'width' => 480,
        'height' => 480,
        'upscale' => FALSE,
      ],
    ]);
    $large_style->save();

    // Create a responsive image style with only one breakpoint (no media query).
    // This will result in output_image_tag = TRUE (img with srcset, not picture).
    $responsive_image_style = ResponsiveImageStyle::create([
      'id' => 'test_single_source',
      'label' => 'Test Single Source',
      'breakpoint_group' => 'responsive_image_test_module',
      'fallback_image_style' => 'large',
    ]);
    // Use the 'empty' breakpoint which has no media query.
    $responsive_image_style
      ->addImageStyleMapping('responsive_image_test_module.empty', '1x', [
        'image_mapping_type' => 'image_style',

        // Use medium (220x220), not large (480x480)
        'image_mapping' => 'medium',
      ])
      ->save();

    // Create an image field.
    $field_name = $this->randomMachineName();
    $this->createImageField($field_name, 'node', 'article', ['uri_scheme' => 'public']);

    // Create a test image.
    $test_image = current($this->getTestFiles('image', 39325));
    $alt = $this->randomMachineName();

    // Upload a node with the image.
    $nid = $this->uploadNodeImage($test_image, $field_name, 'article', $alt);
    $node = $node_storage->load($nid);

    // Configure the display to use the responsive image formatter.
    $display_options = [
      'type' => 'responsive_image',
      'settings' => [
        'image_link' => '',
        'responsive_image_style' => 'test_single_source',
      ],
    ];
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display = $display_repository->getViewDisplay('node', 'article');
    $display->setComponent($field_name, $display_options)->save();

    // Get the image URI.
    $image_uri = File::load($node->{$field_name}->target_id)->getFileUri();

    // Create the image derivatives.
    $medium_style->createDerivative($image_uri, $medium_style->buildUri($image_uri));
    $large_style->createDerivative($image_uri, $large_style->buildUri($image_uri));

    // View the node.
    $this->drupalGet('node/' . $nid);

    // The output should be an img tag with srcset (not a picture element),
    // because there's only one source with no media attribute.
    $this->assertSession()->responseNotContains('<picture>');
    $this->assertSession()->responseContains('<img');
    $this->assertSession()->responseContains('srcset=');

    // The img tag should use dimensions from medium style (220x220),
    // NOT from the fallback large style (480x480).
    // The original image is 360x240, scaled to fit 220x220 gives 220x147.
    $this->assertSession()->responseContains('width="220"');
    $this->assertSession()->responseContains('height="147"');

    // Verify the fallback large style dimensions (480x320) are NOT present.
    $this->assertSession()->responseNotContains('width="480"');
    $this->assertSession()->responseNotContains('height="320"');
  }

}
