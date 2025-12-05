<?php

declare(strict_types=1);

namespace Drupal\Tests\content_translation\Functional;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the "Flag as outdated" functionality with revision translations.
 *
 * @group content_translation
 */
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
    /** @var \Drupal\Core\Entity\TranslatableRevisionableStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage($this->entityTypeId);

    // Create a test node.
    $values = [
      'title' => 'Test 1.1 EN',
      'moderation_state' => 'published',
    ];
    $id = $this->createEntity($values, 'en');
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $storage->load($id);

    /** @var \Drupal\content_translation\ContentTranslationManagerInterface $translation_manager */
    $translation_manager = \Drupal::service(ContentTranslationManagerInterface::class);

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
    $entity = $storage->loadUnchanged($id);
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

    // Store the latest affected revisions for Italian and French.
    $latest_affected_revision_id_it = $storage->getLatestTranslationAffectedRevisionId($id, 'it');
    $latest_affected_revision_id_fr = $storage->getLatestTranslationAffectedRevisionId($id, 'fr');

    // Create another English draft and mark the other translations as outdated.
    $edit = [
      'title[0][value]' => 'Test 1.7 EN',
      'moderation_state[0][state]' => 'draft',
      'content_translation[retranslate]' => 1,
    ];
    $this->drupalGet($en_edit_url);
    $this->submitForm($edit, $this->t('Save (this translation)'));

    // Assert that the stored Italian revision is outdated.
    $latest_affected_revision_it = $storage->loadRevision($latest_affected_revision_id_it)->getTranslation('it');
    $this->assertTrue($translation_manager->getTranslationMetadata($latest_affected_revision_it)->isOutdated());

    // Assert that the stored French revision is outdated.
    $latest_affected_revision_fr = $storage->loadRevision($latest_affected_revision_id_fr)->getTranslation('fr');
    $this->assertTrue($translation_manager->getTranslationMetadata($latest_affected_revision_fr)->isOutdated());

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

    // Assert the latest affected revision id for It is not the one we have
    // stored.
    $this->assertNotEquals($latest_affected_revision_id_it, $storage->getLatestTranslationAffectedRevisionId($id, 'it'));

    // Update the stored latest affected revision translation.
    $latest_affected_revision_id_it = $storage->getLatestTranslationAffectedRevisionId($id, 'it');

    // Assert that the stored Italian revision is marked not outdated.
    $latest_affected_revision_it = $storage->loadRevision($latest_affected_revision_id_it)->getTranslation('it');
    $this->assertFalse($translation_manager->getTranslationMetadata($latest_affected_revision_it)->isOutdated());

    // Publish the French node and mark it as not outdated anymore.
    $edit = [
      'title[0][value]' => 'Test 1.9 FR',
      'moderation_state[0][state]' => 'published',
      'content_translation[outdated]' => 0,
    ];
    $this->drupalGet($fr_edit_url);
    $this->submitForm($edit, $this->t('Save (this translation)'));

    // Assert the latest affected revision id for Fr is not the one we have
    // stored.
    $this->assertNotEquals($latest_affected_revision_id_fr, $storage->getLatestTranslationAffectedRevisionId($id, 'fr'));

    // Update the stored French revision.
    $latest_affected_revision_id_fr = $storage->getLatestTranslationAffectedRevisionId($id, 'fr');

    // Assert that the stored French revision is marked not outdated.
    $latest_affected_revision_fr = $storage->loadRevision($latest_affected_revision_id_fr)->getTranslation('fr');
    $this->assertFalse($translation_manager->getTranslationMetadata($latest_affected_revision_fr)->isOutdated());

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

    $this->drupalGet($en_edit_url);
    $this->assertSession()->pageTextContains('Test 1.10 EN');

    // Verify that the outdated flag is read from the last affected
    // translation revision.
    $this->drupalGet($it_edit_url);
    $this->assertSession()->pageTextContains('Test 1.8 IT');
    $this->assertSession()->pageTextContains('This translation needs to be updated');

    // Assert that the stored Italian revision is marked outdated.
    $latest_affected_revision_it = $storage->loadRevision($latest_affected_revision_id_it)->getTranslation('it');
    $this->assertTrue($translation_manager->getTranslationMetadata($latest_affected_revision_it)->isOutdated());

    // Mark the Italian node as not outdated anymore.
    $edit = [
      'title[0][value]' => 'Test 1.11 IT',
      'content_translation[outdated]' => 0,
    ];
    $this->drupalGet($it_edit_url);
    $this->submitForm($edit, $this->t('Save (this translation)'));

    // Assert the Italian revision we have stored is no longer the latest
    // affected revision.
    $this->assertNotEquals($latest_affected_revision_id_it, $storage->getLatestTranslationAffectedRevisionId($id, 'it'));

    // Update the stored Italian revision.
    $latest_affected_revision_id_it = $storage->getLatestTranslationAffectedRevisionId($id, 'it');

    // Assert that the stored Italian revision is marked up to date.
    $latest_affected_revision_it = $storage->loadRevision($latest_affected_revision_id_it)->getTranslation('it');
    $this->assertFalse($translation_manager->getTranslationMetadata($latest_affected_revision_it)->isOutdated());

    // Verify that the outdated flag is read from the last affected
    // translation revision.
    $this->drupalGet($fr_edit_url);
    $this->assertSession()->pageTextContains('Test 1.9 FR');
    $this->assertSession()->pageTextContains('This translation needs to be updated');

    // Re-save the French node, keeping it outdated. We want to test that
    // re-saving it in the same state removes the outdated status from the
    // old affection revision.
    $edit = [
      'title[0][value]' => 'Test 1.12 FR',
      'content_translation[outdated]' => 1,
    ];
    $this->drupalGet($fr_edit_url);
    $this->submitForm($edit, $this->t('Save (this translation)'));

    // Assert that the stored French version is no longer the latest affected
    // revision.
    $this->assertNotEquals($latest_affected_revision_id_fr, $storage->getLatestTranslationAffectedRevisionId($id, 'fr'));

    // Assert that the old stored French revision is marked not outdated.
    $latest_affected_revision_fr = $storage->loadRevision($latest_affected_revision_id_fr)->getTranslation('fr');
    $this->assertFalse($translation_manager->getTranslationMetadata($latest_affected_revision_fr)->isOutdated());

    // Update the stored French revision.
    $latest_affected_revision_id_fr = $storage->getLatestTranslationAffectedRevisionId($id, 'fr');

    // Assert that the stored French revision is marked outdated.
    $latest_affected_revision_fr = $storage->loadRevision($latest_affected_revision_id_fr)->getTranslation('fr');
    $this->assertTrue($translation_manager->getTranslationMetadata($latest_affected_revision_fr)->isOutdated());

    // Mark the French node as not outdated anymore.
    $edit = [
      'title[0][value]' => 'Test 1.13 FR',
      'content_translation[outdated]' => 0,
    ];
    $this->drupalGet($fr_edit_url);
    $this->submitForm($edit, $this->t('Save (this translation)'));

    // Assert that the stored French version is no longer the latest affected
    // revision.
    $this->assertNotEquals($latest_affected_revision_id_fr, $storage->getLatestTranslationAffectedRevisionId($id, 'fr'));

    // Update the stored French revision.
    $latest_affected_revision_id_fr = $storage->getLatestTranslationAffectedRevisionId($id, 'fr');

    // Assert that the stored French revision is marked not outdated.
    $latest_affected_revision_fr = $storage->loadRevision($latest_affected_revision_id_fr)->getTranslation('fr');
    $this->assertFalse($translation_manager->getTranslationMetadata($latest_affected_revision_fr)->isOutdated());

    // Now re-verify that the outdated flags are as they should be.
    $this->drupalGet($en_edit_url);
    $this->assertSession()->pageTextContains('Test 1.10 EN');
    $this->drupalGet($it_edit_url);
    $this->assertSession()->pageTextContains('Test 1.11 IT');
    $this->assertSession()->pageTextContains('Flag other translations as outdated');
    $this->drupalGet($fr_edit_url);
    $this->assertSession()->pageTextContains('Test 1.13 FR');
    $this->assertSession()->pageTextContains('Flag other translations as outdated');

  }

}
