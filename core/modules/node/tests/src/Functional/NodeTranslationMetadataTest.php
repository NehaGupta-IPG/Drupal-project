<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Functional;

use Drupal\file\Entity\File;
use Drupal\Tests\content_translation\Functional\ContentTranslationTestBase;
use Drupal\Tests\language\Traits\LanguageTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests the Node Translation metadata sync.
 *
 * @group node
 */
class NodeTranslationMetadataTest extends ContentTranslationTestBase {

  use LanguageTestTrait;
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'language',
    'content_translation',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->entityTypeId = 'node';
    $this->bundle = 'article';
    parent::setUp();

    // Create the bundle.
    $this->drupalCreateContentType(['type' => 'article', 'title' => 'Article']);
    $this->doSetup();

    // Display the language selector.
    static::enableBundleTranslation('node', 'article');
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests content translation metadata test.
   */
  public function testImageFieldTranslation(): void {
    // Create an image field.
    \Drupal::service('module_installer')->install(['image']);
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_image',
      'type' => 'image',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_image',
      'bundle' => 'article',
      'translatable' => TRUE,
    ])->save();

    $images = [
      current($this->getTestFiles('image')),
      current($this->getTestFiles('image')),
      current($this->getTestFiles('image')),
    ];
    foreach ($images as $image) {
      $file = File::create((array) $image);
      $file->set('uid', 1);
      $file->save();
      $files[] = $file->id();
    }
    $node = Node::create([
      'type' => 'article',
      'langcode' => 'en',
      'uid' => 1,
      'title' => 'My title',
    ]);
    foreach ($files as $image) {
      $node->field_image[] = [
        'target_id' => $image,
        'alt' => 'My image alt',
        'title' => 'My image title',
      ];
    }
    $node->save();
    $node_es = $node->addTranslation($this->langcodes[1]);
    $node_es->title = 'ES My title';
    foreach ($files as $image) {
      $node_es->field_image[] = [
        'target_id' => $image,
        'alt' => 'lorem ipsum es',
        'title' => 'lorem ipsum es title',
      ];
    }
    $node_es->save();
  }

}
