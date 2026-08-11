<?php

declare(strict_types=1);

namespace Drupal\Tests\content_moderation\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\block_content\Functional\Views\BlockContentTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Tests moderated block content administration page functionality.
 *
 * @group content_moderation
 */
class ModeratedBlocksViewTest extends BlockContentTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $permissions = [
    'access administration pages',
    'view any unpublished content',
    'administer blocks',
  ];

  /**
   * {@inheritdoc}
   */
  protected $autoCreateBasicBlockType = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'block_content',
    'content_moderation',
    'views',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Sets the test up.
   */
  protected function setUp($import_test_views = TRUE, $modules = ['block_content_test_views']): void {
    parent::setUp(FALSE);

    // A 'basic' block type is created by the parent.
    $this->createBlockContentType(['id' => 'custom']);
    $this->createBlockContentType(['id' => 'unmoderated']);

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('block_content', 'basic');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('block_content', 'custom');
    $workflow->save();
  }

  /**
   * Tests the moderated block content page.
   */
  public function testModeratedBlockContentPage() :void {
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->adminUser);

    // Use an explicit changed time to ensure the expected order in the block
    // content admin listing. We want these to appear in the table in the same
    // order as they appear in the following code, and the 'moderated_blocks'
    // view has a table style configuration with a default sort on the 'changed'
    // field descending.
    $time = \Drupal::time()->getRequestTime();
    $excluded_entities['published_basic'] = $this->createBlockContent([
      'type' => 'basic',
      'changed' => $time--,
      'moderation_state' => 'published',
    ]);
    $excluded_entities['published_custom'] = $this->createBlockContent([
      'type' => 'custom',
      'changed' => $time--,
      'moderation_state' => 'published',
    ]);

    $excluded_entities['unmoderated'] = $this->createBlockContent([
      'type' => 'unmoderated',
      'changed' => $time--,
    ]);
    $excluded_entities['unmoderated']->setNewRevision(TRUE);
    $excluded_entities['unmoderated']->isDefaultRevision(FALSE);
    $excluded_entities['unmoderated']->changed->value = $time--;
    $excluded_entities['unmoderated']->save();

    $entities['published_then_draft_basic'] = $this->createBlockContent([
      'type' => 'basic',
      'changed' => $time--,
      'moderation_state' => 'published',
      'info' => 'first basic - published',
    ]);
    $entities['published_then_draft_basic']->setNewRevision(TRUE);
    $entities['published_then_draft_basic']->setInfo('first basic - draft');
    $entities['published_then_draft_basic']->moderation_state->value = 'draft';
    $entities['published_then_draft_basic']->changed->value = $time--;
    $entities['published_then_draft_basic']->save();

    $entities['published_then_archived_basic'] = $this->createBlockContent([
      'type' => 'basic',
      'changed' => $time--,
      'moderation_state' => 'published',
    ]);
    $entities['published_then_archived_basic']->setNewRevision(TRUE);
    $entities['published_then_archived_basic']->moderation_state->value = 'archived';
    $entities['published_then_archived_basic']->changed->value = $time--;
    $entities['published_then_archived_basic']->save();

    $entities['draft_basic'] = $this->createBlockContent([
      'type' => 'basic',
      'changed' => $time--,
      'moderation_state' => 'draft',
    ]);
    $entities['draft_custom_1'] = $this->createBlockContent([
      'type' => 'custom',
      'changed' => $time--,
      'moderation_state' => 'draft',
    ]);
    $entities['draft_custom_2'] = $this->createBlockContent([
      'type' => 'custom',
      'changed' => $time,
      'moderation_state' => 'draft',
    ]);

    // Verify view, edit, and delete links for any block content.
    $this->drupalGet('admin/structure/block/block-content/moderated');
    $assert_session->statusCodeEquals(200);

    // Check that entities with pending revisions appear in the view.
    $block_type_labels = $this->xpath('//td[contains(@class, "views-field-type")]');
    $delta = 0;
    foreach ($entities as $entity) {
      $assert_session->pageTextContains($entity->label());
      $assert_session->linkByHrefExists('block/' . $entity->id());
      $assert_session->linkByHrefExists('block/' . $entity->id() . '/delete');
      // Verify that we can see the block content type label.
      $this->assertEquals($entity->type->entity->label(), trim($block_type_labels[$delta]->getText()));
      $delta++;
    }

    // Check that entities that are not moderated or do not have a pending
    // revision do not appear in the view.
    foreach ($excluded_entities as $entity) {
      $assert_session->pageTextNotContains($entity->label());
    }

    // Check that the latest revision is displayed.
    $assert_session->pageTextContains('first basic - draft');
    $assert_session->pageTextNotContains('first basic - published');

    // Verify filtering by moderation state.
    $this->drupalGet('admin/structure/block/block-content/moderated', ['query' => ['moderation_state' => 'editorial-draft']]);

    $assert_session->linkByHrefExists('block/' . $entities['published_then_draft_basic']->id());
    $assert_session->linkByHrefExists('block/' . $entities['draft_basic']->id());
    $assert_session->linkByHrefExists('block/' . $entities['draft_custom_1']->id());
    $assert_session->linkByHrefExists('block/' . $entities['draft_custom_1']->id());
    $assert_session->linkByHrefNotExists('block/' . $entities['published_then_archived_basic']->id());

    // Verify filtering by moderation state and block content type.
    $this->drupalGet('admin/structure/block/block-content/moderated', ['query' => ['moderation_state' => 'editorial-draft', 'type' => 'custom']]);

    $assert_session->linkByHrefExists('block/' . $entities['draft_custom_1']->id());
    $assert_session->linkByHrefExists('block/' . $entities['draft_custom_2']->id());
    $assert_session->linkByHrefNotExists('block/' . $entities['published_then_draft_basic']->id());
    $assert_session->linkByHrefNotExists('block/' . $entities['published_then_archived_basic']->id());
    $assert_session->linkByHrefNotExists('block/' . $entities['draft_basic']->id());
  }

  /**
   * Tests the moderated blocks content page with multilingual content.
   */
  public function testModeratedBlockContentPageMultilingual() :void {
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $block = $this->createBlockContent([
      'type' => 'basic',
      'moderation_state' => 'draft',
    ]);
    $block->info = 'en draft revision';
    $block->save();

    $translation = BlockContent::load($block->id())->addTranslation('fr');
    $translation->info = 'fr draft revision';
    $translation->moderation_state = 'draft';
    $translation->save();

    $this->drupalLogin($this->adminUser);

    // The moderated block content view should show both the pending en draft
    // revision and the pending fr draft revision.
    $this->drupalGet('admin/structure/block/block-content/moderated');
    $this->assertSession()->linkExists('fr draft revision');
    $this->assertSession()->linkExists('en draft revision');
  }

}
