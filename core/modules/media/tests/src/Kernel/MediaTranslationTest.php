<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\content_translation\ContentTranslationHandler;

/**
 * Tests multilingual fields logic.
 *
 * @group media
 */
class MediaTranslationTest extends MediaKernelTestBase {
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language' , 'content_translation'];

  /**
   * The test media translation type.
   *
   * @var \Drupal\media\MediaTypeInterface
   */
  protected $testTranslationMediaType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['language']);

    // Create a test media type for translations.
    $this->testTranslationMediaType = $this->createMediaType('test_translation');

    for ($i = 0; $i < 3; ++$i) {
      $language_id = 'l' . $i;
      ConfigurableLanguage::create([
        'id' => $language_id,
        'label' => $this->randomString(),
      ])->save();
      file_put_contents('public://' . $language_id . '.png', '');
    }
  }

  /**
   * Tests media title is not overridden when resaved.
   */
  public function testMediaNameNotOverridden(): void {
    ConfigurableLanguage::createFromLangcode('nl')->save();

    // Create two test image files: one for the media item in its original
    // language, and one in its Dutch translation.
    $images = $this->getTestFiles('image');
    $this->assertGreaterThan(1, count($images));

    $original_image = File::create([
      'uri' => reset($images)->uri,
    ]);
    $original_image->save();

    $translated_image = File::create([
      'uri' => end($images)->uri,
    ]);
    $translated_image->save();

    $media_type = $this->createMediaType('image', [
      'field_map' => [
        'name' => 'name',
      ],
    ]);
    $source_field_name = $media_type->getSource()
      ->getSourceFieldDefinition($media_type)
      ->getName();

    // The media's name should be derived from the filename of the image.
    $media = Media::create([
      'bundle' => $media_type->id(),
      $source_field_name => $original_image->id(),
    ]);
    $media->save();
    $this->assertSame($original_image->getFilename(), $media->getName());

    // Create a Dutch translation which uses a different image, but also sets
    // an arbitrary title.
    $translation = $media->addTranslation('nl');
    $this->assertNotSame($media->language()->getId(), $translation->language()->getId());
    $translation->set($source_field_name, $translated_image->id());
    $translation->setName('Capricious and arbitrary');
    $translation->save();
    // The arbitrary title should be preserved.
    $this->assertSame('Capricious and arbitrary', $translation->getName());

    // The original version should be unaffected by Dutch shenanigans.
    $this->container->get('entity_type.manager')
      ->getStorage('media')
      ->resetCache();
    $this->assertSame($original_image->getFilename(), Media::load($media->id())->getName());
  }

  /**
   * Tests translatable fields storage/retrieval.
   */
  public function testTranslatableFieldSaveLoad(): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
    $entity_type = $this->container->get('entity_type.manager')->getDefinition('media');
    $this->assertTrue($entity_type->isTranslatable(), 'Media is translatable.');

    // Check if the translation handler uses the content_translation handler.
    $translation_handler_class = $entity_type->getHandlerClass('translation');
    $this->assertEquals(ContentTranslationHandler::class, $translation_handler_class, 'Translation handler is set to use the content_translation handler.');

    // Prepare the field translations.
    $source_field_definition = $this->testTranslationMediaType->getSource()->getSourceFieldDefinition($this->testTranslationMediaType);
    $source_field_storage = $source_field_definition->getFieldStorageDefinition();
    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $media_storage */
    $media_storage = $this->container->get('entity_type.manager')->getStorage('media');
    /** @var \Drupal\media\Entity\Media $media */
    $media = $media_storage->create([
      'bundle' => $this->testTranslationMediaType->id(),
      'name' => 'Unnamed',
    ]);

    $field_translations = [];
    $available_langcodes = array_keys($this->container->get('language_manager')->getLanguages());
    $media->set('langcode', reset($available_langcodes));
    foreach ($available_langcodes as $langcode) {
      $values = [];
      for ($i = 0; $i < $source_field_storage->getCardinality(); $i++) {
        $values[$i]['value'] = $this->randomString();
      }
      $field_translations[$langcode] = $values;
      $translation = $media->hasTranslation($langcode) ? $media->getTranslation($langcode) : $media->addTranslation($langcode);
      $translation->{$source_field_definition->getName()}->setValue($field_translations[$langcode]);
    }

    // Save and reload the field translations.
    $media->save();
    $media_storage->resetCache();
    $media = $media_storage->load($media->id());

    // Check if the correct source field values were saved/loaded.
    foreach ($field_translations as $langcode => $items) {
      /** @var \Drupal\media\MediaInterface $media_translation */
      $media_translation = $media->getTranslation($langcode);
      $result = TRUE;
      foreach ($items as $delta => $item) {
        $result = $result && $item['value'] == $media_translation->{$source_field_definition->getName()}[$delta]->value;
      }
      $this->assertTrue($result, "$langcode translation field value not correct.");
      $this->assertSame('public://' . $langcode . '.png', $media_translation->getSource()->getMetadata($media_translation, 'thumbnail_uri'), "$langcode translation thumbnail metadata attribute is not correct.");
      $this->assertSame('public://' . $langcode . '.png', $media_translation->get('thumbnail')->entity->getFileUri(), "$langcode translation thumbnail value is not correct.");
      $this->assertEquals('Test Thumbnail ' . $langcode, $media_translation->getSource()->getMetadata($media_translation, 'test_thumbnail_alt'), "$langcode translation thumbnail alt metadata attribute is not correct.");
      $this->assertSame('Test Thumbnail ' . $langcode, $media_translation->get('thumbnail')->alt, "$langcode translation thumbnail alt value is not correct.");
    }
  }

}
