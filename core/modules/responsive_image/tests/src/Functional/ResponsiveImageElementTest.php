<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_image\Functional;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\Tests\image\Functional\ImageFieldTestBase;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests responsive image render element.
 */
#[Group('responsive_image')]
#[RunTestsInSeparateProcesses]
class ResponsiveImageElementTest extends ImageFieldTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Responsive image style entity instance we test with.
   */
  protected ResponsiveImageStyle $responsiveImgStyle;

  /**
   * The file URL generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'responsive_image',
    'responsive_image_test_module',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fileUrlGenerator = $this->container->get('file_url_generator');

    // Create user.
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
    // Add responsive image style.
    $this->responsiveImgStyle = ResponsiveImageStyle::create([
      'id' => 'style_one',
      'label' => 'Style One',
      'breakpoint_group' => 'responsive_image_test_module',
      'fallback_image_style' => 'large',
    ]);
  }

  /**
   * Tests formatter does not throw an exception when the file can't be found.
   */
  public function testResponsiveImageFieldFormatterImageNotFound(): void {
    $large_style = ImageStyle::load('large');
    $this->responsiveImgStyle
      ->addImageStyleMapping('responsive_image_test_module.empty', '2x', [
        'image_mapping_type' => 'sizes',
        'image_mapping' => [
          'sizes' => '(min-width: 700px) 700px, 100vw',
          'sizes_image_styles' => [$large_style->id()],
        ],
      ])
      ->save();

    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $field_name = $this->randomMachineName();
    $this->createImageField($field_name, 'node', 'article', ['uri_scheme' => 'public']);

    // Create a new node with an image attached.
    $test_image = current($this->getTestFiles('image'));
    $this->uploadNodeImage($test_image, $field_name, 'article', $this->randomMachineName());
    $this->assertFileExists($test_image->uri);

    // Delete the image file.
    $this->assertTrue(\Drupal::service('file_system')
      ->delete($test_image->uri));
    $this->assertFileDoesNotExist($test_image->uri);

    $image = [
      '#theme' => 'responsive_image',
      '#responsive_image_style_id' => $this->responsiveImgStyle->id(),
      '#uri' => $test_image->uri,
    ];
    $html_output = str_replace("\n", '', (string) $renderer->renderRoot($image));
    $large_transform_url = $this->fileUrlGenerator->transformRelative($large_style->buildUrl($test_image->uri));
    $this->assertEquals(
      '<img srcset="" src="' . $large_transform_url . '" alt="" />',
      trim($html_output)
    );
  }

}
