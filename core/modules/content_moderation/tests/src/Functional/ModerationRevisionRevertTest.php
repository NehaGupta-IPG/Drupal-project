<?php

declare(strict_types=1);

namespace Drupal\Tests\content_moderation\Functional;

use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test revision revert.
 */
#[Group('content_moderation')]
#[RunTestsInSeparateProcesses]
class ModerationRevisionRevertTest extends BrowserTestBase {

  use ContentTypeCreationTrait;
  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Node entity storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * An array of admin permissions.
   *
   * @var array
   */
  protected $adminPermissions = [
    'access content overview',
    'administer nodes',
    'bypass node access',
    'view all revisions',
    'use editorial transition create_new_draft',
    'use editorial transition publish',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $moderated_bundle = $this->createContentType(['type' => 'moderated_bundle']);
    $moderated_bundle->save();

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'moderated_bundle');
    $workflow->save();

    /** @var \Drupal\Core\Routing\RouteBuilderInterface $router_builder */
    $router_builder = $this->container->get('router.builder');
    $router_builder->rebuildIfNeeded();

    $admin = $this->drupalCreateUser($this->adminPermissions);
    $this->drupalLogin($admin);

    $this->nodeStorage = $this->container->get('entity_type.manager')->getStorage('node');
  }

  /**
   * Tests that reverting a revision works.
   */
  public function testEditingAfterRevertRevision(): void {
    // Create a draft.
    $this->drupalGet('node/add/moderated_bundle');
    $this->submitForm([
      'title[0][value]' => 'First draft node',
      'moderation_state[0][state]' => 'draft',
    ], 'Save');
    $node = $this->getNodeByTitle('First draft node');

    // Now make it published.
    $this->drupalGet('node/1/edit');
    $this->submitForm([
      'title[0][value]' => 'Published node',
      'moderation_state[0][state]' => 'published',
    ], 'Save');

    // Check the editing form that show the published title.
    $this->drupalGet('node/1/edit');
    $this->assertSession()
      ->pageTextContains('Published node');

    // Revert the first revision.
    $revision_url = 'node/1/revisions/1/revert';
    $this->drupalGet($revision_url);
    $this->assertSession()->elementExists('css', '.form-submit');
    $this->click('.form-submit');

    // Ensure the reverted revision remained a draft.
    $this->nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    $revision = $this->nodeStorage->loadRevision($this->nodeStorage->getLatestRevisionId($node->id()));
    $this->assertEquals('draft', $revision->moderation_state->value);

    // @todo, test reverting to a published revision remains published.
    // Check that it reverted.
    $this->drupalGet('node/1/edit');
    $this->assertSession()
      ->pageTextContains('First draft node');
    // Try to save the node.
    $this->drupalGet('node/1/edit');
    $this->submitForm(['moderation_state[0][state]' => 'draft'], 'Save');

    // Check if the submission passed the EntityChangedConstraintValidator.
    $this->assertSession()
      ->pageTextNotContains('The content has either been modified by another user, or you have already submitted modifications. As a result, your changes cannot be saved.');

    // Check the node has been saved.
    $this->assertSession()
      ->pageTextContains('moderated_bundle First draft node has been updated');
  }

  /**
   * Test reverting to a chosen state.
   */
  public function testRevertingToState() {
    $this->drupalGet('node/add/moderated_bundle');
    $this->submitForm([
      'title[0][value]' => 'First revision (originally draft)',
      'moderation_state[0][state]' => 'draft',
    ], 'Save');
    $draft_revision = $this->getNodeByTitle('First revision (originally draft)');
    $node_id = $draft_revision->id();

    $this->drupalGet('node/1/edit');
    $this->submitForm([
      'title[0][value]' => 'Second revision (originally published)',
      'moderation_state[0][state]' => 'published',
    ], 'Save');
    $published_revision = $this->getNodeByTitle('Second revision (originally published)');

    $this->getRevisionRevert($draft_revision);
    $this->assertSession()->fieldNotExists('revert_state');

    $this->loginAsAdmin(['revert editorial revisions to published']);
    $this->getRevisionRevert($draft_revision);
    $this->assertSession()->optionExists('revert_state', 'published');
    $this->assertSession()->optionNotExists('revert_state', 'draft');

    // Test reverting from a draft to a published revision.
    $this->getRevisionRevert($draft_revision);
    $this->submitForm(['revert_state' => 'published'], 'Revert');
    $this->assertLatestRevision($node_id, 'published', TRUE, TRUE);

    $this->loginAsAdmin([
      'revert editorial revisions to published',
      'revert editorial revisions to draft',
    ]);
    $this->getRevisionRevert($draft_revision);
    $this->assertSession()->optionExists('revert_state', 'draft');
    $this->assertSession()->optionExists('revert_state', 'published');

    // Test reverting from a draft to a draft revision.
    $this->getRevisionRevert($draft_revision);
    $this->submitForm(['revert_state' => 'draft'], 'Revert');
    $this->assertLatestRevision($node_id, 'draft', FALSE, FALSE);

    // Test reverting from a pending draft to a published revision.
    $this->clickLink('Set as current revision');
    $this->submitForm(['revert_state' => 'published'], 'Revert');
    $this->assertLatestRevision($node_id, 'published', TRUE, TRUE);

    // Test reverting from a published revision to a draft revision.
    $this->getRevisionRevert($published_revision);
    $this->submitForm(['revert_state' => 'draft'], 'Revert');
    $this->assertLatestRevision($node_id, 'draft', FALSE, FALSE);

    // Test reverting from a published revision to a published revision.
    $this->getRevisionRevert($published_revision);
    $this->submitForm(['revert_state' => 'published'], 'Revert');
    $this->assertLatestRevision($node_id, 'published', TRUE, TRUE);
  }

  /**
   * Login as an admin, with additional permissions.
   *
   * @param array $additional_permissions
   *   Additional permissions to add to the admin permissions.
   *
   * @return \Drupal\user\Entity\User
   *   The new account that was created.
   */
  protected function loginAsAdmin($additional_permissions = []) {
    $account = $this->drupalCreateUser(array_merge($this->adminPermissions, $additional_permissions));
    $this->drupalLogin($account);
    return $account;
  }

  /**
   * Visit the URL which will revert to the given.
   *
   * @param \Drupal\node\NodeInterface $node_revision
   *   The node revision to revert to.
   */
  protected function getRevisionRevert(NodeInterface $node_revision) {
    $this->drupalGet("node/{$node_revision->id()}/revisions/{$node_revision->getRevisionId()}/revert");
  }

  /**
   * Assert the latest node revision matches the given parameters.
   *
   * @param int $id
   *   The ID of the node to load and assert.
   * @param string $moderation_state
   *   The moderation state.
   * @param bool $published
   *   If the revision is published.
   * @param bool $default
   *   If the revision is default.
   *
   * @return \Drupal\node\NodeInterface
   *   The revision which was asserted.
   */
  protected function assertLatestRevision($id, $moderation_state, $published, $default) {
    $this->nodeStorage->resetCache();
    /** @var \Drupal\node\NodeInterface $revision */
    $revision = $this->nodeStorage->loadRevision($this->nodeStorage->getLatestRevisionId($id));
    $this->assertEquals($moderation_state, $revision->moderation_state->value);
    $this->assertEquals($published, $revision->isPublished());
    $this->assertEquals($default, $revision->isDefaultRevision());
    return $revision;
  }

}
