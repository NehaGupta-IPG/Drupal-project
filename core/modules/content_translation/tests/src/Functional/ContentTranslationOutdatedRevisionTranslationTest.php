<?php

declare(strict_types=1);

namespace Drupal\Tests\content_translation\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "Flag as outdated" functionality with revision translations.
 */
#[Group('content_translation')]
#[RunTestsInSeparateProcesses]
class ContentTranslationOutdatedRevisionTranslationTest extends ContentTranslationPendingRevisionTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->doSetup();
    $this->enableContentModeration();
  }

  /**
   * Tests that outdated revision translations work correctly.
   */
  public function testFlagAsOutdated(): void {
    // Create a test node.
    $values = [
      'title' => 'Test 1.1 EN',
      'moderation_state' => 'published',
    ];
    $id = $this->createEntity($values, 'en');
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->storage->load($id);

    // Add a published Italian translation.
    $add_translation_url = Url::fromRoute("entity.{$this->entityTypeId}.content_translation_add",
      [
        $entity->getEntityTypeId() => $id,
        'source' => 'en',
        'target' => 'it',
      ],
      [
        'language' => ConfigurableLanguage::load('it'),
        'absolute' => FALSE,
      ]
    );
    $this->drupalGet($add_translation_url);
    $edit = [
      'title[0][value]' => 'Test 1.2 IT',
      'moderation_state[0][state]' => 'published',
    ];
    $this->submitForm($edit, 'Save (this translation)');

    // Add a published French translation.
    $add_translation_url = Url::fromRoute("entity.{$this->entityTypeId}.content_translation_add",
      [
        $entity->getEntityTypeId() => $id,
        'source' => 'en',
        'target' => 'fr',
      ],
      [
        'language' => ConfigurableLanguage::load('fr'),
        'absolute' => FALSE,
      ]
    );
    $this->drupalGet($add_translation_url);
    $edit = [
      'title[0][value]' => 'Test 1.3 FR',
      'moderation_state[0][state]' => 'published',
    ];
    $this->submitForm($edit, 'Save (this translation)');

    // Create an English draft.
    $edit = [
      'title[0][value]' => 'Test 1.4 EN',
      'moderation_state[0][state]' => 'draft',
    ];
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->storage->loadUnchanged($id);
    $en_edit_url = $this->getEditUrl($entity);
    $this->drupalGet($en_edit_url);
    $this->submitForm($edit, 'Save (this translation)');
    // Create an Italian draft.
    $edit = [
      'title[0][value]' => 'Test 1.5 IT',
      'moderation_state[0][state]' => 'draft',
    ];
    $it_edit_url = $this->getEditUrl($entity->getTranslation('it'));
    $this->drupalGet($it_edit_url);
    $this->submitForm($edit, $this->t('Save (this translation)'));

    // Create a French draft.
    $edit = [
      'title[0][value]' => 'Test 1.6 FR',
      'moderation_state[0][state]' => 'draft',
    ];
    $fr_edit_url = $this->getEditUrl($entity->getTranslation('fr'));
    $this->drupalGet($fr_edit_url);
    $this->submitForm($edit, $this->t('Save (this translation)'));

    // Create another English draft and mark the other translations as outdated.
    $edit = [
      'title[0][value]' => 'Test 1.7 EN',
      'moderation_state[0][state]' => 'draft',
      'content_translation[retranslate]' => 1,
    ];
    $this->drupalGet($en_edit_url);
    $this->submitForm($edit, $this->t('Save (this translation)'));

    // Verify that the expected translation drafts are loaded in the related
    // entity forms and that the translations are marked as needing an update.
    $this->drupalGet($en_edit_url);
    $this->assertSession()->pageTextContains('Test 1.7 EN');
    $this->drupalGet($it_edit_url);
    $this->assertSession()->pageTextContains('Test 1.5 IT');
    $this->assertSession()->pageTextContains('This translation needs to be updated');
    $this->drupalGet($fr_edit_url);
    $this->assertSession()->pageTextContains('Test 1.6 FR');
    $this->assertSession()->pageTextContains('This translation needs to be updated');

    // Keep the Italian node as a draft but mark it as not outdated.
    $edit = [
      'title[0][value]' => 'Test 1.8 IT',
      'moderation_state[0][state]' => 'draft',
      'content_translation[outdated]' => 0,
    ];
    $this->drupalGet($it_edit_url);
    $this->submitForm($edit, $this->t('Save (this translation)'));

    // Publish the French node and mark it as not outdated anymore.
    $edit = [
      'title[0][value]' => 'Test 1.9 FR',
      'moderation_state[0][state]' => 'published',
      'content_translation[outdated]' => 0,
    ];
    $this->drupalGet($fr_edit_url);
    $this->submitForm($edit, $this->t('Save (this translation)'));

    // Verify that the outdated flag is no longer checked on the translations.
    $this->drupalGet($it_edit_url);
    $this->assertSession()->pageTextContains('Test 1.8 IT');
    $this->assertSession()->pageTextContains('Flag other translations as outdated');
    $this->drupalGet($fr_edit_url);
    $this->assertSession()->pageTextContains('Test 1.9 FR');
    $this->assertSession()->pageTextContains('Flag other translations as outdated');

    // Re-mark the translations as outdated on the English node and publish
    // the changes.
    $edit = [
      'title[0][value]' => 'Test 1.10 EN',
      'moderation_state[0][state]' => 'published',
      'content_translation[retranslate]' => 1,
    ];
    $this->drupalGet($en_edit_url);
    $this->submitForm($edit, $this->t('Save (this translation)'));

    // Verify that the outdated flag is read from the last affected
    // translation revision.
    $this->drupalGet($en_edit_url);
    $this->assertSession()->pageTextContains('Test 1.10 EN');
    $this->drupalGet($it_edit_url);
    $this->assertSession()->pageTextContains('Test 1.8 IT');
    $this->assertSession()->pageTextContains('This translation needs to be updated');
    $this->drupalGet($fr_edit_url);
    $this->assertSession()->pageTextContains('Test 1.9 FR');
    $this->assertSession()->pageTextContains('This translation needs to be updated');

    // Mark the Italian node as not outdated anymore.
    $edit = [
      'title[0][value]' => 'Test 1.11 IT',
      'content_translation[outdated]' => 0,
    ];
    $this->drupalGet($it_edit_url);
    $this->submitForm($edit, $this->t('Save (this translation)'));

    // Mark the French node as not outdated anymore.
    $edit = [
      'title[0][value]' => 'Test 1.12 FR',
      'content_translation[outdated]' => 0,
    ];
    $this->drupalGet($fr_edit_url);
    $this->submitForm($edit, $this->t('Save (this translation)'));

    // Now re-verify that the outdated flags are as they should be.
    $this->drupalGet($en_edit_url);
    $this->assertSession()->pageTextContains('Test 1.10 EN');
    $this->drupalGet($it_edit_url);
    $this->assertSession()->pageTextContains('Test 1.11 IT');
    $this->assertSession()->pageTextContains('Flag other translations as outdated');
    $this->drupalGet($fr_edit_url);
    $this->assertSession()->pageTextContains('Test 1.12 FR');
    $this->assertSession()->pageTextContains('Flag other translations as outdated');

  }

}
