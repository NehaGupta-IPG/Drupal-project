<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Entity;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests entity translation functionality related to publishing.
 *
 * @group Entity
 */
class EntityTranslationPublishTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $node1;

  /**
   * {@inheritdoc}
   */
  protected $contentType;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'content_translation',
    'entity_test',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->contentType = 'page';
    $this->drupalCreateContentType(['type' => $this->contentType, 'name' => 'Basic page']);
    $this->node1 = $this->drupalCreateNode([
      'type' => $this->contentType,
      'title' => 'Unpublished node',
      'status' => 0,
      'langcode' => 'en',
    ]);
    // Need admin user to be able to access block admin.
    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'edit own page content',
      'create page content',
    ]);
  }

  /**
   * Creates an unpublished node and checks there's no access to it.
   */
  public function testUnpublishedNodeAccess(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalLogout();
    $this->drupalGet('node/' . $this->node1->id());
    $this->assertSession()->pageTextContains('Access denied');
  }

  /**
   * Creates a published node and checks there's access to it.
   */
  public function testPublishedNodeAccess(): void {
    $this->drupalLogin($this->adminUser);
    $this->node1->set('status', 1);
    $this->node1->set('title', 'Published node');
    $this->node1->save();

    $this->drupalLogout();
    $this->drupalGet('node/' . $this->node1->id());
    $this->assertSession()->pageTextContains('Published node');
  }

  /**
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testUnpublishedTranslationAccess(): void {
    $this->drupalLogin($this->adminUser);
    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();

    $english_title = 'Published node';
    $french_title = 'French title';
    $spanish_title = 'Spanish title';

    $node = $this->drupalCreateNode([
      'type' => $this->contentType,
      'title' => $english_title,
      'status' => 1,
      'langcode' => 'en',
    ]);
    // Now I should be able to access this node in English.
    $this->drupalLogout();
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->pageTextContains($english_title);
    // Then I add a French translation.
    $this->drupalLogin($this->adminUser);
    $nodeFr = $node->addTranslation('fr');
    $nodeFr->setTitle($french_title);
    $nodeFr->save();
    $this->drupalLogout();
    // Now I should be able to access this node in French.
    $this->drupalGet('fr/node/' . $node->id());
    $this->assertSession()->pageTextContains($french_title);
    // Add a Spanish translation, unpublished.
    $this->drupalLogin($this->adminUser);
    $nodeEs = $node->addTranslation('es', [
      'title' => $spanish_title,
      'status' => 0,
    ]);
    $nodeEs->save();
    // Now I shouldn't get there.
    $this->drupalLogout();
    $this->drupalGet('es/node/' . $nodeEs->id());
    $this->assertSession()->pageTextContains('Access denied');
    // But I should get to the French translation still.
    $this->drupalGet('fr/node/' . $nodeFr->id());
    $this->assertSession()->pageTextContains($french_title);
    // And I should get to the English node as well.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->pageTextContains($english_title);
    // Then I try publishing the Spanish version.
    $this->drupalLogin($this->adminUser);
    $nodeEs->set('status', 1);
    $nodeEs->save();
    $this->drupalLogout();
    $this->drupalGet('es/node/' . $nodeEs->id());
    $this->assertSession()->pageTextContains($spanish_title);
    // Unpublishing the French one.
    $this->drupalLogin($this->adminUser);
    $nodeFr->set('status', 0);
    $nodeFr->save();
    $this->drupalLogout();
    $this->drupalGet('fr/node/' . $nodeFr->id());
    $this->assertSession()->pageTextContains('Access denied');
    // I should see the Spanish node.
    $this->drupalGet('es/node/' . $nodeEs->id());
    $this->assertSession()->pageTextContains($spanish_title);
    // I should see the English node.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->pageTextContains($english_title);
  }

}
