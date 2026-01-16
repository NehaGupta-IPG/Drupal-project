<?php

declare(strict_types=1);

namespace Drupal\Tests\content_moderation\Functional;

use Drupal\Tests\content_translation\Traits\ContentTranslationTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests EntityChangedConstraint with content moderation and translations.
 *
 * This test ensures that editing translations of moderated content does not
 * trigger false "content modified by another user" validation errors when
 * working with different revisions.
 *
 * @see https://www.drupal.org/project/drupal/issues/3285657
 */
#[Group('content_moderation')]
#[Group('slow')]
#[RunTestsInSeparateProcesses]
class ModerationTranslationValidationTest extends ModerationStateTestBase {

  use ContentTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'content_moderation',
    'locale',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions(): array {
    return array_merge($this->permissions, [
      'create content translations',
      'bypass node access',
      'translate any entity',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser($this->getAdministratorPermissions());
    $this->drupalLogin($this->adminUser);

    // Create content type and enable moderation via the base class method.
    $this->createContentTypeFromUi('Article', 'article', TRUE);

    // Add French language.
    static::createLanguageFromLangcode('fr');

    // Enable content translation on articles.
    $this->enableContentTranslation('node', 'article');

    // Adding languages requires a container rebuild in the test running
    // environment so that multilingual services are used.
    $this->rebuildContainer();
  }

  /**
   * Tests editing a translation after archiving the default translation.
   *
   * When the default (English) translation is archived, it creates a new
   * revision with a newer timestamp. Editing the French translation should
   * not trigger a false "content modified" error because the revisions are
   * different.
   */
  public function testEditTranslationAfterArchivingDefaultTranslation(): void {
    // Create a published article in English.
    $this->drupalGet('node/add/article');
    $edit = [
      'title[0][value]' => 'Test article',
      'moderation_state[0][state]' => 'published',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Article Test article has been created.');

    $node = $this->drupalGetNodeByTitle('Test article');
    $nid = $node->id();

    // Add a French translation (published).
    $this->drupalGet("node/{$nid}/translations");
    $this->clickLink('Add');
    $edit = [
      'title[0][value]' => 'Article de test',
      'moderation_state[0][state]' => 'published',
    ];
    $this->submitForm($edit, 'Save (this translation)');
    $this->assertSession()->pageTextContains('Article Article de test has been updated.');

    // Archive the English translation. This creates a new revision with a
    // newer timestamp than the French translation's last edit.
    $this->drupalGet("node/{$nid}/edit");
    $edit = [
      'moderation_state[0][state]' => 'archived',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Article Test article has been updated.');

    // Now edit the French translation. This should NOT trigger the
    // "content modified by another user" error, even though the default
    // revision has a newer timestamp.
    $this->drupalGet("fr/node/{$nid}/edit");
    $edit = [
      'title[0][value]' => 'Article de test modifié',
      'moderation_state[0][state]' => 'published',
    ];
    $this->submitForm($edit, 'Save (this translation)');

    // Verify no false "content modified" error.
    $this->assertSession()->pageTextNotContains('The content has either been modified by another user, or you have already submitted modifications');
    // Verify the save succeeded.
    $this->assertSession()->pageTextContains('Article Article de test modifié has been updated.');
  }

  /**
   * Tests editing a translation after publishing another translation.
   *
   * When the French translation is published after the English translation,
   * it creates a new default revision. Editing the English translation should
   * not trigger a false validation error.
   */
  public function testEditTranslationAfterPublishingAnotherTranslation(): void {
    // Create a published article in English.
    $this->drupalGet('node/add/article');
    $edit = [
      'title[0][value]' => 'English article',
      'moderation_state[0][state]' => 'published',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Article English article has been created.');

    $node = $this->drupalGetNodeByTitle('English article');
    $nid = $node->id();

    // Add a French translation and publish it immediately.
    // This creates a new default revision with a newer timestamp.
    $this->drupalGet("node/{$nid}/translations");
    $this->clickLink('Add');
    $edit = [
      'title[0][value]' => 'Article français',
      'moderation_state[0][state]' => 'published',
    ];
    $this->submitForm($edit, 'Save (this translation)');
    $this->assertSession()->pageTextContains('Article Article français has been updated.');

    // Edit the English translation. This should not trigger a false
    // "content modified" validation error, even though the French translation
    // was published more recently.
    $this->drupalGet("node/{$nid}/edit");
    $edit = [
      'title[0][value]' => 'English article updated',
      'moderation_state[0][state]' => 'published',
    ];
    $this->submitForm($edit, 'Save');

    // Verify no false "content modified" error.
    $this->assertSession()->pageTextNotContains('The content has either been modified by another user, or you have already submitted modifications');
    // Verify the save succeeded.
    $this->assertSession()->pageTextContains('Article English article updated has been updated.');
  }

}
