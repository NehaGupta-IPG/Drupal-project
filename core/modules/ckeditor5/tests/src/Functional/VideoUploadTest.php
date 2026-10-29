<?php

declare(strict_types=1);

namespace Drupal\Tests\ckeditor5\Functional;

use Drupal\ckeditor5\Plugin\Editor\CKEditor5;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\editor\Entity\Editor;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Tests the video media insertion on ckeditor.
 *
 * @group ckeditor5
 * @internal
 */
class VideoUploadTest extends BrowserTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_ui',
    'media',
    'media_library',
    'ckeditor5',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FilterFormat::create([
      'format' => 'test_format',
      'name' => 'Test format',
      'filters' => [
        'media_embed' => ['status' => TRUE],
      ],
    ])->save();
    Editor::create([
      'editor' => 'ckeditor5',
      'format' => 'test_format',
      'settings' => [
        'toolbar' => [
          'items' => [
            'drupalMedia',
          ],
        ],
        'plugins' => [
          'media_media' => [
            'allow_view_mode_override' => FALSE,
          ],
        ],
      ],
    ])->save();
    $this->assertSame([], array_map(
      function (ConstraintViolation $v) {
        return (string) $v->getMessage();
      },
      iterator_to_array(CKEditor5::validatePair(
        Editor::load('test_format'),
        FilterFormat::load('test_format')
      ))
    ));

    // Create node type blog.
    $this->drupalCreateContentType(['type' => 'blog']);
    // Creating video_type type media.
    $this->createVideoMediaType(['id' => 'video_type']);
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests using drupalMedia button to embed media into CKEditor 5.
   */
  public function testVideoMediaAdding() {
    $video_element_xpath = '//video[@controls="controls"]';

    // Enabling advanced UI inside Media Library settings, helps to add 'Save and insert' button.
    $config = \Drupal::configFactory()->getEditable('media_library.settings');
    $config->set('advanced_ui', TRUE)->save();
    \Drupal::service('plugin.cache_clearer')->clearCachedDefinitions();

    // Create a video asset.
    file_put_contents('public://file.mp4', str_repeat('t', 10));

    // Disabling "Display" field option.
    FieldStorageConfig::load('media.field_media_video_file')
      ->setSetting('display_field', FALSE)
      ->setSetting('display_default', FALSE)
      ->save();

    // Adding a video media to the editor and checking for video element.
    $this->drupalGet('/node/add/blog');
    $this->assertNotEmpty($this->getDrupalSettings());
    $settings = $this->getDrupalSettings();
    $this->assertTrue(
      isset($settings['editor']['formats']['test_format']['editorSettings']['config']['drupalMedia']['libraryURL']),
      'Media url not found inside the node adding page.'
    );
    $media_library_url = $settings['editor']['formats']['test_format']['editorSettings']['config']['drupalMedia']['libraryURL'];
    $this->getSession()->getDriver()->getClient()->request('POST', $media_library_url, ['js' => TRUE, '_drupal_ajax' => 1]);
    $this->assertSession()->statusCodeEquals(200);
    $edit = [
      'files[upload][]' => \Drupal::service('file_system')->realpath('public://file.mp4'),
    ];
    $this->submitForm($edit, 'Upload');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Save and insert');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('xpath', $video_element_xpath);

    // Enabling "Display" field option.
    FieldStorageConfig::load('media.field_media_video_file')
      ->setSetting('display_field', TRUE)
      ->setSetting('display_default', TRUE)
      ->save();

    // Adding a video media to the editor and checking for video element.
    $this->drupalGet('/node/add/blog');
    $this->assertNotEmpty($this->getDrupalSettings());
    $settings = $this->getDrupalSettings();
    $this->assertTrue(
      isset($settings['editor']['formats']['test_format']['editorSettings']['config']['drupalMedia']['libraryURL']),
      'Media url not found inside the node adding page.'
    );
    $media_library_url = $settings['editor']['formats']['test_format']['editorSettings']['config']['drupalMedia']['libraryURL'];
    $this->getSession()->getDriver()->getClient()->request('POST', $media_library_url, ['js' => TRUE, '_drupal_ajax' => 1]);
    $this->assertSession()->statusCodeEquals(200);
    $edit = [
      'files[upload][]' => \Drupal::service('file_system')->realpath('public://file.mp4'),
    ];
    $this->submitForm($edit, 'Upload');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Save and insert');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('xpath', $video_element_xpath);
  }

  /**
   * Create a video media type.
   *
   * @param mixed[] $values
   *   (optional) Additional values for the media type entity:
   *   - id: The ID of the media type. If none is provided, a random value will
   *     be used.
   *   - label: The human-readable label of the media type. If none is provided,
   *     a random value will be used.
   *   See \Drupal\media\MediaTypeInterface and \Drupal\media\Entity\MediaType
   *   for full documentation of the media type properties.
   *
   * @return \Drupal\media\MediaTypeInterface
   *   A media type.
   */
  protected function createVideoMediaType(array $values = []) {
    // Creating video media.
    $media_type = $this->createMediaType('video_file', $values);

    // Make the entity to show only video field on display.
    EntityViewDisplay::create([
      'targetEntityType' => 'media',
      'bundle' => 'video_type',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_media_video_file', [
      'type' => 'file_video',
      'label' => 'visually_hidden',
    ])->removeComponent('thumbnail')
      ->removeComponent('uid')
      ->removeComponent('created')
      ->save();

    // Create media_library display mode for the entity.
    EntityFormDisplay::create([
      'targetEntityType' => 'media',
      'bundle' => 'video_type',
      'mode' => 'media_library',
      'status' => TRUE,
    ])->save();

    return $media_type;
  }

}
