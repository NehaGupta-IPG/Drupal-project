<?php

declare(strict_types=1);

namespace Drupal\Tests\content_translation\Functional;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Test adding new translation with pending revisions.
 *
 * @group content_translation
 */
class ContentTranslationAddTranslationTest extends ContentTranslationPendingRevisionTestBase {

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
   * Tests that a translation can be added from an older source translation.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testWithOlderSourceTranslation() {
    $time = \Drupal::time()->getRequestTime();

    // Create English node.
    // (nid=1, latest vid=1, default vid=1, enLAT=1, itLAT=NULL).
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->storage->create([
      'title' => 'Test Node',
      'type' => 'article',
      'langcode' => 'en',
      'moderation_state' => 'published',
      'created' => $time - 10,
      'changed' => $time - 10,
    ]);
    $node->save();

    // Add Italian translation.
    // (nid=1, latest vid=2, default vid=2, enLAT=1, itLAT=2).
    $id = $node->id();
    $it = $node->addTranslation('it', [
      'title' => 'Test Node (it)',
      'moderation_state' => 'published',
      'changed' => $time - 8,
    ]);
    $it->save();

    // Re-save EN so its LAT is older than the default.
    // (nid=1, vid=3, default vid=3, enLAT=3, itLAT=2).
    /** @var \Drupal\node\Entity\Node $en */
    $en = $this->storage->loadUnchanged($id);
    $en->setNewRevision();
    $en->changed = $time - 6;
    $en->save();

    // Save a pending revision of English.
    // (nid=1, vid=4, default vid=3, enLAT=4, itLAT=2).
    $en->setNewRevision();
    $en->moderation_state = 'draft';
    $en->changed = $time - 4;
    $en->save();

    // Add a French Translation using Italian as the source.
    $add_translation_url = Url::fromRoute("entity.{$this->entityTypeId}.content_translation_add", [
      'node' => $id,
      'source' => 'it',
      'target' => 'fr',
    ],
      [
        'language' => ConfigurableLanguage::load('fr'),
        'absolute' => FALSE,
      ]
    );
    $this->drupalGet($add_translation_url);
    $edit = [
      'title[0][value]' => 'Test Node (fr)',
      'moderation_state[0][state]' => 'published',
    ];
    $this->submitForm($edit, 'Save (this translation)');
    $this->assertSession()->pageTextNotContains("The content has either been modified by another user, or you have already submitted modifications. As a result, your changes cannot be saved.");
    $this->assertSession()->pageTextContains('article Test Node (fr) has been updated');
  }

}
