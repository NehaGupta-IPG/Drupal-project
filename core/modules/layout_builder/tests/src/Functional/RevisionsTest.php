<?php

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\layout_builder\Traits\EnableLayoutBuilderTrait;
use Drupal\user\Entity\User;

/**
 * Tests the Layout Builder revisions behavior.
 *
 * @group layout_builder
 */
class RevisionsTest extends BrowserTestBase {

  use EnableLayoutBuilderTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create two content types.
    $this->createContentType(['type' => 'bundle_with_revisions', 'new_revision' => TRUE]);
    $this->createContentType(['type' => 'bundle_without_revisions', 'new_revision' => FALSE]);
  }

  /**
   * Tests that default revision settings are respected.
   */
  public function testRevisions(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'access user profiles',
    ]));

    $display = LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'user',
      'bundle' => 'user',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->save();
    $this->enableLayoutBuilder($display);
    $this->enableLayoutBuilder(LayoutBuilderEntityViewDisplay::load('node.bundle_with_revisions.default'));
    $this->enableLayoutBuilder(LayoutBuilderEntityViewDisplay::load('node.bundle_without_revisions.default'));

    // Create a node of the bundle that will have new revisions by default.
    $revision_node = $this->createNode([
      'type' => 'bundle_with_revisions',
      'title' => 'The first node title',
      'body' => [
        [
          'value' => 'The first node body',
        ],
      ],
    ]);
    // Create a node of the bundle that will NOT have new revisions by default.
    $no_revisions_node = $this->createNode([
      'type' => 'bundle_without_revisions',
      'title' => 'The second node title',
      'body' => [
        [
          'value' => 'The second node body',
        ],
      ],
    ]);
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Ensure that saving a layout for a node where the bundle defaults to
    // saving new revisions causes a new revision to be saved.
    $revision_id_before_new_revision = $node_storage->getLatestRevisionId($revision_node->id());
    $this->saveLayoutOverride($revision_node);
    $node_storage->resetCache([$revision_node->id()]);
    $this->assertGreaterThan($revision_id_before_new_revision, $node_storage->getLatestRevisionId($revision_node->id()));

    // Ensure that saving a layout for a node of the bundle that does NOT save a
    // new revision by default does NOT create a new revision.
    $revision_id_before_save_same_revision = $node_storage->getLatestRevisionId($no_revisions_node->id());
    $this->saveLayoutOverride($no_revisions_node);
    $this->assertEquals($revision_id_before_save_same_revision, $node_storage->getLatestRevisionId($no_revisions_node->id()));

    // Ensure that saving an entity type that does not support revisions is
    // successful.
    $this->saveLayoutOverride(User::load(1));
  }

  /**
   * Saves a layout override for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  protected function saveLayoutOverride(EntityInterface $entity): void {
    $this->drupalGet($entity->toUrl()->toString() . '/layout');
    $this->getSession()->getPage()->pressButton('Save layout');
    $this->assertSession()->pageTextContains('The layout override has been saved.');
  }

}
