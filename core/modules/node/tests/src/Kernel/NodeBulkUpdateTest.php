<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\NodeBulkUpdate;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the NodeBulkUpdate service.
 */
#[Group('node')]
#[RunTestsInSeparateProcesses]
class NodeBulkUpdateTest extends NodeAccessTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'user', 'language', 'content_translation'];

  /**
   * The node bulk update service.
   *
   * @var \Drupal\node\NodeBulkUpdate
   */
  protected NodeBulkUpdate $nodeBulkUpdate;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The installConfig line you had is correct, provided the modules above
    // are enabled.
    $this->installConfig(['language', 'content_translation']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    // The service now exists because the 'node' module is enabled.
    $this->nodeBulkUpdate = $this->container->get(NodeBulkUpdate::class);

  }

  /**
   * Tests updating fewer than 10 nodes without batch processing.
   */
  public function testBulkUpdateWithoutBatch(): void {
    // Create 5 nodes.
    $nodes = [];
    for ($i = 0; $i < 5; $i++) {
      $nodes[] = $this->drupalCreateNode([
        'type' => 'page',
        'title' => 'Original Title ' . $i,
        'status' => 1,
      ]);
    }

    // Update all nodes to unpublished.
    $nids = array_map(fn($node) => $node->id(), $nodes);
    $this->nodeBulkUpdate->process($nids, ['status' => 0], NULL, TRUE, FALSE);

    // Verify all nodes are now unpublished.
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    foreach ($nids as $nid) {
      $node = $storage->load($nid);
      $this->assertFalse($node->isPublished(), "Node $nid should be unpublished.");
    }
  }

  /**
   * Tests updating more than 10 nodes with batch processing.
   */
  public function testBulkUpdateWithBatch(): void {
    // Create 15 nodes to trigger batch processing.
    $nodes = [];
    for ($i = 0; $i < 15; $i++) {
      $nodes[] = $this->drupalCreateNode([
        'type' => 'page',
        'title' => 'Original Title ' . $i,
        'status' => 1,
        'promote' => 0,
      ]);
    }

    $nids = array_map(fn($node) => $node->id(), $nodes);

    // Simulate batch processing manually since we can't run batch_set in
    // kernel tests.
    $context = [];
    NodeBulkUpdate::batchProcess($nids, ['status' => 0, 'promote' => 1], NULL, TRUE, FALSE, $context);

    // Process should complete in one iteration for 15 nodes (processes 5 at a time).
    while ($context['finished'] < 1) {
      NodeBulkUpdate::batchProcess($nids, ['status' => 0, 'promote' => 1], NULL, TRUE, FALSE, $context);
    }

    // Verify all nodes are updated.
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    foreach ($nids as $nid) {
      $node = $storage->load($nid);
      $this->assertFalse($node->isPublished(), "Node $nid should be unpublished.");
      $this->assertTrue($node->isPromoted(), "Node $nid should be promoted.");
    }

    // Test batch finished callback.
    NodeBulkUpdate::batchFinished(TRUE, $context['results']);
    $messages = $this->container->get('messenger')->all();
    $this->assertNotEmpty($messages['status'], 'Success message should be set.');
  }

  /**
   * Tests bulk update with a deleted node during batch processing.
   *
   * This tests the fix for issue #3456528 where a node deleted during
   * batch processing would cause the process to fail.
   */
  public function testBulkUpdateWithDeletedNode(): void {
    // Create nodes.
    $nodes = [];
    for ($i = 0; $i < 15; $i++) {
      $nodes[] = $this->drupalCreateNode([
        'type' => 'page',
        'title' => 'Node ' . $i,
        'status' => 1,
      ]);
    }

    $nids = array_map(fn($node) => $node->id(), $nodes);

    // Delete one node to simulate the issue.
    $deleted_node = array_shift($nodes);
    $deleted_node->delete();

    // Initialize batch context.
    $context = [];

    // Process the batch with the deleted node still in the array.
    // This should not throw an error.
    NodeBulkUpdate::batchProcess($nids, ['status' => 0], NULL, TRUE, FALSE, $context);

    // Continue processing until complete.
    while ($context['finished'] < 1) {
      NodeBulkUpdate::batchProcess($nids, ['status' => 0], NULL, TRUE, FALSE, $context);
    }

    // Verify remaining nodes are updated and deleted node was skipped.
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    foreach ($nodes as $node) {
      $loaded_node = $storage->load($node->id());
      $this->assertNotNull($loaded_node, 'Node should still exist.');
      $this->assertFalse($loaded_node->isPublished(), 'Node should be unpublished.');
    }

    // Verify results don't include the deleted node.
    $this->assertCount(14, $context['results'], 'Only 14 nodes should be in results (deleted node excluded).');
  }

  /**
   * Tests bulk update with deleted revision during batch processing.
   */
  public function testBulkUpdateWithDeletedRevision(): void {
    // Create nodes with multiple revisions.
    $revision_ids = [];
    $old_revision_id_to_delete = NULL;

    for ($i = 0; $i < 12; $i++) {
      $node = $this->drupalCreateNode([
        'type' => 'page',
        'title' => 'Node ' . $i,
        'status' => 1,
        'promote' => 0,
      ]);

      // Store the first old revision ID to delete later.
      if ($i === 0) {
        $old_revision_id_to_delete = $node->getRevisionId();
      }

      // Create a new revision (this becomes the default).
      $node->setNewRevision(TRUE);
      $node->setTitle('Node ' . $i . ' - Revision 2');
      $node->setPromoted(FALSE);
      $node->save();

      // Store the current (default) revision IDs for batch processing.
      $revision_ids[] = $node->getRevisionId();
    }

    // Delete the old (non-default) revision of the first node.
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->deleteRevision($old_revision_id_to_delete);

    // Now simulate deleting one of the current revision IDs to test the actual
    // fix. We'll replace one revision ID with the deleted one to simulate
    // the scenario.
    $revision_ids[0] = $old_revision_id_to_delete;

    // Initialize batch context.
    $context = [];

    // Process the batch with revisions, including the deleted one.
    // Use a safe field update that doesn't affect revision status.
    NodeBulkUpdate::batchProcess($revision_ids, ['promote' => 1], NULL, TRUE, TRUE, $context);

    // Continue processing until complete.
    while ($context['finished'] < 1) {
      NodeBulkUpdate::batchProcess($revision_ids, ['promote' => 1], NULL, TRUE, TRUE, $context);
    }

    // Verify the batch completed without errors & skipped the deleted revision.
    // Should have 11 results (12 total minus 1 deleted).
    $this->assertCount(11, $context['results'], 'Only 11 revisions should be in results (deleted revision excluded).');
  }

  /**
   * Tests bulk update with multilingual nodes.
   */
  public function testBulkUpdateMultilingual(): void {
    // Add a second language.
    ConfigurableLanguage::createFromLangcode('fr')->save();

    // Enable translation for page content type.
    $this->container->get('content_translation.manager')
      ->setEnabled('node', 'page', TRUE);

    // Create a node with translations.
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'English Title',
      'status' => 1,
      'langcode' => 'en',
    ]);

    $node->addTranslation('fr', [
      'title' => 'French Title',
      'status' => 1,
    ]);
    $node->save();

    // Update only the French translation.
    $this->nodeBulkUpdate->process(
      [$node->id()],
      ['status' => 0],
      'fr',
      TRUE,
      FALSE
    );

    // Reload the node.
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $updated_node = $storage->load($node->id());

    // Verify English version is still published.
    $this->assertTrue($updated_node->isPublished(), 'English translation should still be published.');

    // Verify French version is unpublished.
    $french_translation = $updated_node->getTranslation('fr');
    $this->assertFalse($french_translation->isPublished(), 'French translation should be unpublished.');
  }

  /**
   * Tests bulk update with all translations when langcode is NULL.
   */
  public function testBulkUpdateAllTranslations(): void {
    // Add a second language.
    ConfigurableLanguage::createFromLangcode('de')->save();

    // Enable translation for page content type.
    $this->container->get('content_translation.manager')
      ->setEnabled('node', 'page', TRUE);

    // Create a node with translations.
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'English Title',
      'promote' => 0,
      'langcode' => 'en',
    ]);

    $node->addTranslation('de', [
      'title' => 'German Title',
      'promote' => 0,
    ]);
    $node->save();

    // Update all translations (langcode is NULL).
    $this->nodeBulkUpdate->process(
      [$node->id()],
      ['promote' => 1],
      NULL,
      TRUE,
      FALSE
    );

    // Reload the node.
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $updated_node = $storage->load($node->id());

    // Verify both translations are promoted.
    $this->assertTrue($updated_node->isPromoted(), 'English translation should be promoted.');
    $german_translation = $updated_node->getTranslation('de');
    $this->assertTrue($german_translation->isPromoted(), 'German translation should be promoted.');
  }

  /**
   * Tests bulk update with preloaded nodes.
   */
  public function testBulkUpdateWithPreloadedNodes(): void {
    // Create nodes.
    $nodes = [];
    for ($i = 0; $i < 5; $i++) {
      $nodes[] = $this->drupalCreateNode([
        'type' => 'page',
        'title' => 'Node ' . $i,
        'sticky' => 0,
      ]);
    }

    // Update with pre-loaded nodes (load = FALSE).
    $this->nodeBulkUpdate->process(
      $nodes,
      ['sticky' => 1],
      NULL,
      FALSE,
      FALSE
    );

    // Verify all nodes are sticky.
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    foreach ($nodes as $original_node) {
      $node = $storage->load($original_node->id());
      $this->assertTrue($node->isSticky(), "Node {$node->id()} should be sticky.");
    }
  }

  /**
   * Tests batch finished callback with errors.
   */
  public function testBatchFinishedWithErrors(): void {
    $results = ['node:1', 'node:2', 'node:3'];

    // Test failure scenario.
    NodeBulkUpdate::batchFinished(FALSE, $results);

    $messages = $this->container->get('messenger')->all();
    $this->assertNotEmpty($messages['error'], 'Error message should be set on failure.');
    $this->assertNotEmpty($messages['status'], 'Status message with processed items should be set.');
  }

  /**
   * Tests updating multiple node properties simultaneously.
   */
  public function testBulkUpdateMultipleProperties(): void {
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Original Title',
      'status' => 1,
      'promote' => 0,
      'sticky' => 0,
    ]);

    // Update multiple properties at once.
    $this->nodeBulkUpdate->process(
      [$node->id()],
      [
        'status' => 0,
        'promote' => 1,
        'sticky' => 1,
      ],
      NULL,
      TRUE,
      FALSE
    );

    // Reload and verify.
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $updated_node = $storage->load($node->id());

    $this->assertFalse($updated_node->isPublished(), 'Node should be unpublished.');
    $this->assertTrue($updated_node->isPromoted(), 'Node should be promoted.');
    $this->assertTrue($updated_node->isSticky(), 'Node should be sticky.');
  }

}
