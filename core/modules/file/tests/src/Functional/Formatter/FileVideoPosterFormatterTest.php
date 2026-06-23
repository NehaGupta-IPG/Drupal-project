<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Functional\Formatter;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\Plugin\Field\FieldFormatter\FileVideoFormatter;
use Drupal\Tests\file\Functional\FileFieldTestBase;
use Drupal\media\Entity\Media;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests rendering poster on video.
 */
#[CoversClass(FileVideoFormatter::class)]
#[Group('file')]
#[RunTestsInSeparateProcesses]
class FileVideoPosterFormatterTest extends FileFieldTestBase {

  use ImageFieldCreationTrait;
  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'image',
    'media',
  ];

  /**
   * Tests rendering poster on video.
   */
  public function testPosterNoImageStyle(): void {

    // Video field configuration.
    $video_fieldname = 'field_' . mb_strtolower($this->randomMachineName());
    $this->createFileField($video_fieldname, 'node', 'article', [], ['file_extensions' => 'mp4']);

    // Poster image field configuration.
    $poster_fieldname = 'field_' . mb_strtolower($this->randomMachineName());
    $this->createImageField($poster_fieldname, 'node', 'article', [], ['file_extensions' => 'jpg']);

    // Configure node display.
    $display = \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', 'article');
    $display_options = [
      'type' => 'file_video',
      'settings' => [
        'poster' => $poster_fieldname,
        'poster_image_style' => '',
      ],
    ];
    $display->setComponent($video_fieldname, $display_options)
      ->save();

    // Create files.
    file_put_contents('public://file.mp4', str_repeat('t', 10));
    $video1 = File::create([
      'uri' => 'public://file.mp4',
      'filename' => 'file.mp4',
    ]);
    $video1->save();
    $file_url = \Drupal::service('file_url_generator')->generate($video1->getFileUri())->toString();

    file_put_contents('public://file.jpg', str_repeat('t', 10));
    $poster1 = File::create([
      'uri' => 'public://file.jpg',
      'filename' => 'file.jpg',
    ]);
    $poster1->save();
    $poster_url = \Drupal::service('file_url_generator')->generate($poster1->getFileUri())->toString();

    // Create test node.
    $node = $this->drupalCreateNode([
      'title' => 'Hello, world!',
      'type' => 'article',
      $video_fieldname => [
        [
          'target_id' => $video1->id(),
        ],
      ],
      $poster_fieldname => [
        [
          'target_id' => $poster1->id(),
        ],
      ],
    ]);
    $node->save();

    $this->drupalGet('/node/' . $node->id());
    $assert_session = $this->assertSession();
    $assert_session->elementExists('css', "video > source[src='$file_url'][type='video/mp4']");
    $assert_session->elementExists('css', "video[poster='$poster_url']");
  }

  /**
   * Tests rendering poster on video using an image style.
   */
  public function testPosterImageStyle(): void {
    // Video field configuration.
    $video_fieldname = 'field_' . mb_strtolower($this->randomMachineName());
    $this->createFileField($video_fieldname, 'node', 'article', [], ['file_extensions' => 'mp4']);

    // Poster image field configuration.
    $poster_fieldname = 'field_' . mb_strtolower($this->randomMachineName());
    $this->createImageField($poster_fieldname, 'node', 'article', [], ['file_extensions' => 'jpg']);

    // Configure node display.
    $display = \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', 'article');
    $display_options = [
      'type' => 'file_video',
      'settings' => [
        'poster' => $poster_fieldname,
        'poster_image_style' => 'medium',
      ],
    ];
    $display->setComponent($video_fieldname, $display_options)
      ->save();

    // Create files.
    file_put_contents('public://file.mp4', str_repeat('t', 10));
    $video1 = File::create([
      'uri' => 'public://file.mp4',
      'filename' => 'file.mp4',
    ]);
    $video1->save();
    $file_url = \Drupal::service('file_url_generator')->generate($video1->getFileUri())->toString();

    file_put_contents('public://file.jpg', str_repeat('t', 10));
    $poster1 = File::create([
      'uri' => 'public://file.jpg',
      'filename' => 'file.jpg',
    ]);
    $poster1->save();

    // Create test node.
    $node = $this->drupalCreateNode([
      'title' => 'Hello, world!',
      'type' => 'article',
      $video_fieldname => [
        [
          'target_id' => $video1->id(),
        ],
      ],
      $poster_fieldname => [
        [
          'target_id' => $poster1->id(),
        ],
      ],
    ]);
    $node->save();

    $this->drupalGet('/node/' . $node->id());
    $assert_session = $this->assertSession();
    $assert_session->elementExists('css', "video > source[src='$file_url'][type='video/mp4']");
    // Ensure the image style is served:
    $assert_session->elementAttributeContains('css', 'video', 'poster', 'styles/medium/public/file.jpg');
  }

  /**
   * Tests rendering a poster sourced from a media reference field.
   *
   * The poster field can also point at an entity reference field targeting a
   * media entity. When that media exposes an image source file, its URL is
   * rendered as the poster attribute.
   */
  public function testPosterMediaReference(): void {
    $scenario = $this->setUpMediaPosterScenario('image', 'image', 'file.jpg', '');

    $this->drupalGet('/node/' . $scenario['node']->id());
    $assert_session = $this->assertSession();
    $assert_session->elementExists('css', "video > source[src='{$scenario['video_url']}'][type='video/mp4']");
    $assert_session->elementExists('css', "video[poster='{$scenario['poster_url']}']");
  }

  /**
   * Tests a poster sourced from a media reference rendered with an image style.
   *
   * Mirrors testPosterImageStyle() but resolves the poster through a media
   * reference field, asserting the styled derivative URL is used.
   */
  public function testPosterMediaReferenceImageStyle(): void {
    $scenario = $this->setUpMediaPosterScenario('image', 'image', 'file.jpg', 'medium');

    $this->drupalGet('/node/' . $scenario['node']->id());
    $assert_session = $this->assertSession();
    $assert_session->elementExists('css', "video > source[src='{$scenario['video_url']}'][type='video/mp4']");
    // Ensure the styled derivative URL is served for the media poster:
    $assert_session->elementAttributeContains('css', 'video', 'poster', 'styles/medium/public/file.jpg');
  }

  /**
   * Tests that a non-image media reference produces no poster attribute.
   *
   * The poster resolution is image-only: a media reference whose source file is
   * not an image must be skipped, leaving the rendered <video> with no poster
   * attribute. This exercises the image-mime filter and the early bail-out.
   */
  public function testPosterNonImageMediaReference(): void {
    $scenario = $this->setUpMediaPosterScenario('document', 'file', 'file.txt', '');

    $this->drupalGet('/node/' . $scenario['node']->id());
    $assert_session = $this->assertSession();
    $assert_session->elementExists('css', "video > source[src='{$scenario['video_url']}'][type='video/mp4']");
    // The non-image media must be skipped, leaving no poster attribute.
    $assert_session->elementAttributeNotExists('css', 'video', 'poster');
  }

  /**
   * Sets up a video  media-reference-poster scenario and returns the node.
   *
   * @param string $media_bundle
   *   The media type id to create (e.g. 'image' or 'document').
   * @param string $media_source
   *   The media source plugin id ('image' or 'file').
   * @param string $poster_filename
   *   The poster source filename to create (its extension drives the MIME
   *   type).
   * @param string $poster_image_style
   *   The image style id for the formatter 'poster_image_style' setting ('' for
   *   none).
   *
   * @return array
   *   An associative array with the keys:
   *   - node: \Drupal\node\NodeInterface, the created node referencing the
   *     video file and the poster media.
   *   - video_url: string, the generated URL of the video file.
   *   - poster_url: string, the generated URL of the poster source file.
   */
  private function setUpMediaPosterScenario(string $media_bundle, string $media_source, string $poster_filename, string $poster_image_style): array {
    // Create the media type. Its standard source field holds the source file.
    $media_type = $this->createMediaType($media_source, ['id' => $media_bundle]);
    $source_field = $media_type->getSource()->getSourceFieldDefinition($media_type)->getName();

    // Video field configuration.
    $video_fieldname = 'field_' . mb_strtolower($this->randomMachineName());
    $this->createFileField($video_fieldname, 'node', 'article', [], ['file_extensions' => 'mp4']);

    // Media reference (poster) field configuration on the article bundle.
    $poster_fieldname = 'field_' . mb_strtolower($this->randomMachineName());
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => $poster_fieldname,
      'type' => 'entity_reference',
      'cardinality' => 1,
      'settings' => ['target_type' => 'media'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => $poster_fieldname,
      'bundle' => 'article',
      'settings' => [
        'handler_settings' => ['target_bundles' => [$media_bundle => $media_bundle]],
      ],
    ])->save();

    // Configure node display to use the media reference field as the poster.
    $display = \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', 'article');
    $display_options = [
      'type' => 'file_video',
      'settings' => [
        'poster' => $poster_fieldname,
        'poster_image_style' => $poster_image_style,
      ],
    ];
    $display->setComponent($video_fieldname, $display_options)
      ->save();

    // Create the video file.
    file_put_contents('public://file.mp4', str_repeat('t', 10));
    $video1 = File::create([
      'uri' => 'public://file.mp4',
      'filename' => 'file.mp4',
    ]);
    $video1->save();
    $video_url = \Drupal::service('file_url_generator')->generate($video1->getFileUri())->toString();

    // Create the poster source file and wrap it in a media entity.
    file_put_contents('public://' . $poster_filename, str_repeat('t', 10));
    $poster_file = File::create([
      'uri' => 'public://' . $poster_filename,
      'filename' => $poster_filename,
    ]);
    $poster_file->save();
    $poster_url = \Drupal::service('file_url_generator')->generate($poster_file->getFileUri())->toString();

    $media = Media::create([
      'bundle' => $media_bundle,
      'name' => 'Poster media',
      $source_field => ['target_id' => $poster_file->id()],
    ]);
    $media->save();

    // Create test node referencing the video file and the poster media.
    $node = $this->drupalCreateNode([
      'title' => 'Hello, world!',
      'type' => 'article',
      $video_fieldname => [
        ['target_id' => $video1->id()],
      ],
      $poster_fieldname => [
        ['target_id' => $media->id()],
      ],
    ]);
    $node->save();

    return [
      'node' => $node,
      'video_url' => $video_url,
      'poster_url' => $poster_url,
    ];
  }

}
