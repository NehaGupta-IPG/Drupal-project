<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\language\Traits\LanguageTestTrait;

/**
 * Tests the Node Preview with translations.
 *
 * @group node
 */
class NodePreviewTranslationTest extends BrowserTestBase {

  use LanguageTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'title' => 'Article']);
    static::enableBundleTranslation('node', 'article');
    static::createLanguageFromLangcode('es');
    // Rebuild the container so LanguageServiceProvider adds the path
    // processors.
    $this->rebuildContainer();
  }

  /**
   * Tests previewing a node in different languages.
   */
  public function testNodePreviewWithTranslations(): void {
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'langcode' => 'en',
      'title' => 'English node',
    ]);
    $esTranslation = $node->addTranslation('es', $node->toArray());
    $esTranslation->setTitle('Spanish node');
    $esTranslation->save();

    $this->drupalLogin($this->createUser([
      'edit any article content',
    ]));

    $this->drupalGet($esTranslation->toUrl('edit-form'));
    $this->assertSession()->fieldValueEquals('title[0][value]', 'Spanish node');
    // Preview the spanish translation with an updated title.
    $this->submitForm([
      'title[0][value]' => 'Spanish node updated',
    ], 'Preview');

    $this->drupalGet($node->toUrl('edit-form'));
    $this->assertSession()->fieldValueEquals('title[0][value]', 'English node');
    // Preview the english translation with an updated title.
    $this->submitForm([
      'title[0][value]' => 'English node updated',
    ], 'Preview');

    // Simulate clicking the Back to content editing link from the spanish
    // translation.
    $this->drupalGet($esTranslation->toUrl('edit-form', ['query' => ['uuid' => $esTranslation->getPreviewUuid()]]));
    // The title should match the updated title from the first preview.
    $this->assertSession()->fieldValueEquals('title[0][value]', 'Spanish node updated');
  }

}
