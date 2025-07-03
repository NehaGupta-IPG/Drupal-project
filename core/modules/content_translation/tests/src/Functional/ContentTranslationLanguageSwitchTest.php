<?php

namespace Drupal\Tests\content_translation\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests switching the language field on the edit form without existing translation.
 *
 * @group content_translation
 */
class ContentTranslationLanguageSwitchTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'language',
    'content_translation',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to create and edit articles.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * {@inheritdoc}
   */
  protected function configSchemaCheckerExclusions(): array {
    return ['language.settings'];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure the article content type exists.
    if (!NodeType::load('article')) {
      NodeType::create([
        'type' => 'article',
        'name' => 'Article',
      ])->save();
    }

    // Add the German language if it does not exist.
    if (!\Drupal::languageManager()->getLanguage('de')) {
      \Drupal\language\Entity\ConfigurableLanguage::create([
        'id' => 'de',
        'label' => 'German',
      ])->save();
    }

    // Enable content translation for article content type.
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    // Enable language switch on the node edit form.
    $settings = \Drupal\language\Entity\ContentLanguageSettings::loadByEntityTypeBundle('node', 'article');
    if (!$settings) {
      $settings = \Drupal\language\Entity\ContentLanguageSettings::create([
        'target_entity_type_id' => 'node',
        'target_bundle' => 'article',
      ]);
    }
    $settings->setDefaultLangcode('en');
    $settings->setLanguageAlterable(TRUE);
    $settings->save();

    // Display the langcode field on the form.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', 'article', 'default')
      ->setComponent('langcode', ['type' => 'language_select'])
      ->save();

    // Place local tasks block.
    $this->drupalPlaceBlock('local_tasks_block');

    // Create and log in a user with necessary permissions.
    $this->webUser = $this->drupalCreateUser([
      'create article content',
      'edit any article content',
      'translate any entity',
    ]);
    $this->drupalLogin($this->webUser);
  }

  /**
   * Tests switching the language of a node on the edit form.
   */
  public function testLanguageFieldSwitchOnEdit(): void {
    $edit = [
      'title[0][value]' => 'Test Node',
    ];
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');

    $node = Node::load(1);
    $this->assertEquals('en', $node->language()->getId());

    $this->drupalGet('node/1/edit');
    $this->submitForm(['langcode[0][value]' => 'de'], 'Save');

    $node = Node::load(1);
    $this->assertEquals('de', $node->language()->getId());
  }

}
