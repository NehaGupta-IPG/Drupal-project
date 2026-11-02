<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityLanguageTestBase;

/**
 * Tests language related aspects of image formatter.
 *
 * @group image
 */
class ImageFormatterTranslationTest extends EntityLanguageTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['file', 'image', 'content_translation'];

  /**
   * Our test entity.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $entity;

  /**
   * Our test entity, but translated.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $translation;

  /**
   * EntityViewDisplayInterface.
   *
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  protected $display;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Chunk of code from ImageFormatterTest::setUp(),
    // But we also add an untranslatable image field.
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    $entityType = 'entity_test';
    $bundle = $entityType;

    $this->entityTypeManager->getStorage('language_content_settings')->create([
      'target_entity_type_id' => 'entity_test',
      'target_bundle' => 'entity_test',
    ])->save();
    $this->container->get('content_translation.manager')->setEnabled('entity_test', 'entity_test', TRUE);

    FieldStorageConfig::create([
      'entity_type' => $entityType,
      'field_name' => 'field_translatable_image',
      'type' => 'image',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'entity_type' => $entityType,
      'field_name' => 'field_translatable_image',
      'bundle' => $bundle,
      'translatable' => TRUE,
      'settings' => [
        'file_extensions' => 'jpg',
      ],
    ])->save();

    FieldStorageConfig::create([
      'entity_type' => $entityType,
      'field_name' => 'field_untranslatable_image',
      'type' => 'image',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'entity_type' => $entityType,
      'field_name' => 'field_untranslatable_image',
      'bundle' => $bundle,
      'translatable' => FALSE,
      'settings' => [
        'file_extensions' => 'jpg',
      ],
    ])->save();

    $this->display = \Drupal::service('entity_display.repository')
      ->getViewDisplay($entityType, $bundle, 'default')
      ->setComponent('field_untranslatable_image', [
        'type' => 'image',
        'label' => 'hidden',
      ])
      ->setComponent('field_translatable_image', [
        'type' => 'image',
        'label' => 'hidden',
      ]);
    $this->display->save();
    // End of code from ImageFormatterTest::setUp().
    // Chunk of code essentially taken from EntityUrlLanguageTest::setUp().
    \Drupal::service('router.builder')->rebuild();
    // In order to reflect the changes for a multilingual site in the container
    // we have to rebuild it.
    $config = $this->config('language.negotiation');
    $config->set('url.prefixes', ['en' => 'en', 'l0' => 'l0'])
      ->save();

    \Drupal::service('kernel')->rebuildContainer();
    $this->languageManager = $this->container->get('language_manager');
    $this->languageManager->reset();
    // End of code essentially from EntityUrlLanguageTest::setUp().
    // Create our entities.
    $this->entity = EntityTest::create([
      'name' => $this->randomMachineName(),
    ]);
    $this->entity->{'field_untranslatable_image'}->generateSampleItems(2);
    $this->entity->{'field_translatable_image'}->generateSampleItems(2);
    $this->entity->save();

    $this->translation = $this->entity->addTranslation('l0', ['name' => 'Translated Entity', 'field_translatable_image' => $this->entity->get('field_translatable_image')->getValue()]);
    $this->translation->save();
  }

  /**
   * Render translatable field in default language.
   */
  public function testDefaultTranslatableImage(): void {
    $this->display
      ->setComponent('field_translatable_image', [
        'settings' => ['image_link' => 'content'],
      ])
      ->save();
    $this->assertUrlRenderedInCorrectLanguage($this->entity, 'field_translatable_image');
  }

  /**
   * Render untranslatable field in default language.
   */
  public function testDefaultUntranslatableImage(): void {
    $this->display
      ->setComponent('field_untranslatable_image', [
        'settings' => ['image_link' => 'content'],
      ])
      ->save();
    $this->assertUrlRenderedInCorrectLanguage($this->entity, 'field_untranslatable_image');
  }

  /**
   * Render translatable field on translated entity.
   */
  public function testTranslatedTranslatableImage(): void {
    $this->display
      ->setComponent('field_translatable_image', [
        'settings' => ['image_link' => 'content'],
      ])
      ->save();
    $this->assertUrlRenderedInCorrectLanguage($this->translation, 'field_translatable_image');
  }

  /**
   * Render untranslatable field on translated entity.
   */
  public function testTranslatedUntranslatableImage(): void {
    $this->display
      ->setComponent('field_untranslatable_image', [
        'settings' => ['image_link' => 'content'],
      ])
      ->save();
    $this->assertUrlRenderedInCorrectLanguage($this->translation, 'field_untranslatable_image');
  }

  /**
   * Modeled after ImageFormatterTest::testImageFormatterUrlOptions().
   *
   * Consider an entity with an image field. We render the entity in some
   * language. The image field uses the image formatter with a link
   * to the content. We are checking that the link goes to the content
   * in the appropriate language.
   *
   * @param \Drupal\entity_test\Entity\EntityTest $entity_test
   *   A test entity in some language.
   * @param string $field_name
   *   The machine name of the image field being rendered.
   */
  protected function assertUrlRenderedInCorrectLanguage(EntityTest $entity_test, string $field_name): void {
    // First, test that the toUrl() function is behaving as expected.
    $expected_url_string = "/{$entity_test->language()->getId()}/entity_test/{$entity_test->id()}";
    $this->assertEquals($entity_test->toUrl()->toString(), $expected_url_string);

    // Override the language.
    \Drupal::service('language.default')->set($entity_test->language());
    \Drupal::languageManager()->reset();
    \Drupal::languageManager()->getCurrentLanguage();

    // Now we actually start testing the formatter.
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');

    $build = $this->display->build($entity_test);

    $output = $renderer->renderRoot($build[$field_name][0]);
    $this->assertStringContainsString('<a href="' . $entity_test->toUrl()->toString(), (string) $output);
  }

}
