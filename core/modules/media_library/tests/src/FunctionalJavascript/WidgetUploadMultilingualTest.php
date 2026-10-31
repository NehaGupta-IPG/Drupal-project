<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library\FunctionalJavascript;

use Drupal\media\Entity\Media;
use Drupal\Tests\content_translation\Traits\ContentTranslationTestTrait;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests that uploads in the 'media_library_widget' works as expected.
 *
 * These tests address multilingual features that require language and
 * content_translation modules to be installed.
 *
 * @group media_library
 */
class WidgetUploadMultilingualTest extends MediaLibraryTestBase {

  use TestFileCreationTrait;
  use ContentTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language', 'content_translation'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests created medias have the same language as the edited translation.
   */
  public function testWidgetUploadMediaLanguage(): void {

    // Retrieve a test image file.
    foreach ($this->getTestFiles('image') as $image) {
      $extension = pathinfo($image->filename, PATHINFO_EXTENSION);
      if ($extension === 'png') {
        $png_image = $image;
      }
    }
    if (!isset($png_image)) {
      $this->fail('Expected test file not present.');
    }

    // Setup languages.
    static::createLanguageFromLangcode('es');
    $this->container->get('config.factory')->getEditable('language.negotiation')
      ->set('url.source', 'path_prefix')
      ->set('url.prefixes.es', 'es')
      ->save();
    $this->enableContentTranslation('node', 'basic_page');

    // Create a user that can create medias and translate entities.
    $user = $this->drupalCreateUser([
      'access administration pages',
      'access content',
      'create basic_page content',
      'edit any basic_page content',
      'create media',
      'view media',
      'translate any entity',
    ]);
    $this->drupalLogin($user);

    // Create a basic page.
    $node = $this->drupalCreateNode([
      'type' => 'basic_page',
      'title' => 'Test node',
      'langcode' => 'en',
    ]);

    // Check translation add form.
    $this->drupalGet('node/' . $node->id() . '/translations/add/en/es');
    // Open the media library and upload a file.
    $this->openMediaLibraryForField('field_unlimited_media');
    $this->switchToMediaType('Three');
    $this->addMediaFileToField('Add files', $this->container->get('file_system')->realpath($png_image->uri));
    $this->assertMediaAdded();
    $this->waitForFieldExists('Alternative text')->setValue($this->randomString());
    $this->pressSaveButton();
    // Check that the created media item has the same language
    // as the translation being created.
    $media_items = Media::loadMultiple();
    $added_media = array_pop($media_items);
    $this->assertEquals('es', $added_media->language()->getId());
    $this->pressInsertSelected();
    // Press save button on node edit form page
    $this->submitForm([], 'Save (this translation)');
    $this->waitForText('has been updated');

    // Check translation edit form.
    $this->drupalGet('es/node/' . $node->id() . '/edit');
    // Open the media library and upload a file.
    $this->openMediaLibraryForField('field_unlimited_media');
    $this->switchToMediaType('Three');
    $this->addMediaFileToField('Add files', $this->container->get('file_system')->realpath($png_image->uri));
    $this->assertMediaAdded();
    $this->waitForFieldExists('Alternative text')->setValue($this->randomString());
    $this->pressSaveButton();
    // Check that the created media item has the same language
    // as the translation being edited.
    $media_items = Media::loadMultiple();
    $added_media = array_pop($media_items);
    $this->assertEquals('es', $added_media->language()->getId());
  }

}
