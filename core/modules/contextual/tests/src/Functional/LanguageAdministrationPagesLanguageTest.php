<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests administration pages language.
 */
#[Group('language')]
#[RunTestsInSeparateProcesses]
class LanguageAdministrationPagesLanguageTest extends BrowserTestBase {

  use ContentTypeCreationTrait {
    createContentType as drupalCreateContentType;
  }
  use ContextualLinkClickTrait;
  use NodeCreationTrait {
    createNode as drupalCreateNode;
  }

  /**
   * Admin user.
   */
  protected UserInterface $admin;

  /**
   * Test node.
   */
  protected NodeInterface $node;

  /**
   * Block ID.
   */
  protected array $blockId;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'locale',
    'content_translation',
    'block',
    'node',
    'contextual',
    'toolbar',
    'system_test',
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
    // User to manage languages.
    $this->admin = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($this->admin);

    // Create FR.
    ConfigurableLanguage::createFromLangcode('fr')->save();

    // Set language detection to URL and browser detection.
    $this->drupalGet('/admin/config/regional/language/detection');
    $this->submitForm([
      'language_interface[enabled][language-url]' => TRUE,
      'language_interface[enabled][language-user-admin]' => TRUE,
      'language_content[configurable]' => TRUE,
      'language_content[enabled][language-url]' => TRUE,
      'language_content[enabled][language-interface]' => FALSE,
    ], 'Save settings');

    // Set prefixes to en and fr.
    $this->drupalGet('/admin/config/regional/language/detection/url');
    $this->submitForm([
      'prefix[en]' => 'en',
      'prefix[fr]' => 'fr',
    ], 'Save configuration');

    // Create a node type.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);
    $this->node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'New page',
    ]);

    $this->drupalGet('/admin/config/regional/content-language');
    $edit = [
      'entity_types[node]' => TRUE,
      'settings[node][page][translatable]' => TRUE,
    ];
    $this->submitForm($edit, 'Save configuration');

    $local_tasks_block = $this->drupalPlaceBlock('local_tasks_block');
    $page_title_block = $this->drupalPlaceBlock('page_title_block');
    $this->blockId['local_tasks_block'] = $local_tasks_block->id();
    $this->blockId['page_title_block'] = $page_title_block->id();

    $this->drupalGet('/node/' . $this->node->id() . '/translations/add/en/fr');
    $edit = [
      'title[0][value]' => 'Nouvelle page',
    ];
    $this->submitForm($edit, 'Save (this translation)');
  }

  /**
   * Tests administration pages language.
   */
  public function testLanguageAdministrationPagesLanguage(): void {
    $local_tasks_block_prefix = '//div[contains(@data-contextual-id, "block:block=' . $this->blockId['local_tasks_block'];
    $page_title_block_prefix = '//div[contains(@data-contextual-id, "block:block=' . $this->blockId['page_title_block'];

    $this->drupalGet('/node/' . $this->node->id());
    $this->assertSession()->elementsCount('xpath', $local_tasks_block_prefix . ':langcode=en&admin_langcode=en")]', 1);
    $this->assertSession()->elementsCount('xpath', $page_title_block_prefix . ':langcode=en&admin_langcode=en")]', 1);

    $this->drupalGet('/fr/node/' . $this->node->id());
    $this->assertSession()->elementsCount('xpath', $local_tasks_block_prefix . ':langcode=fr&admin_langcode=en")]', 1);
    $this->assertSession()->elementsCount('xpath', $page_title_block_prefix . ':langcode=fr&admin_langcode=en")]', 1);

    $this->drupalGet('/user/' . $this->admin->id() . '/edit');
    $this->submitForm(['preferred_admin_langcode' => 'fr'], 'Save');
    // drupal_flush_all_caches($this->kernel);.
    $this->drupalGet('/node/' . $this->node->id());
    $this->assertSession()->elementsCount('xpath', $local_tasks_block_prefix . ':langcode=en&admin_langcode=fr")]', 1);
    $this->assertSession()->elementsCount('xpath', $page_title_block_prefix . ':langcode=en&admin_langcode=fr")]', 1);

    $this->drupalGet('/fr/node/' . $this->node->id());
    $this->assertSession()->elementsCount('xpath', $local_tasks_block_prefix . ':langcode=fr&admin_langcode=fr")]', 1);
    $this->assertSession()->elementsCount('xpath', $page_title_block_prefix . ':langcode=fr&admin_langcode=fr")]', 1);
  }

}
