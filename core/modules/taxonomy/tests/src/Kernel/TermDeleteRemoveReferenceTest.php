<?php

declare(strict_types=1);

namespace Drupal\Tests\taxonomy\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for taxonomy term reference fields in nodes.
 */
#[Group('taxonomy')]
#[RunTestsInSeparateProcesses]
class TermDeleteRemoveReferenceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'node',
    'user',
    'text',
    'field',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['taxonomy']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', 'node_access');
  }

  /**
   * Tests that the taxonomy term reference fields in nodes clean up on term deletion.
   */
  public function testTaxonomyIndexWithPendingRevision(): void {
    \Drupal::configFactory()->getEditable('taxonomy.settings')->set('maintain_index_table', TRUE)->save();

    Vocabulary::create([
      'name' => 'test',
      'vid' => 'test',
    ])->save();
    $term = Term::create([
      'name' => 'term1',
      'vid' => 'test',
    ]);
    $term->save();
    $term2 = Term::create([
      'name' => 'term2',
      'vid' => 'test',
    ]);
    $term2->save();

    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_tags',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();
    $node = Node::create([
      'type' => 'page',
      'title' => 'test_title',
      'field_tags' => [$term->id()],
    ]);
    $node->save();

    $taxonomy_index = $this->getTaxonomyIndex();
    $this->assertEquals($term->id(), $taxonomy_index[$node->id()]->tid);

    // TODO: Add code to delete a term or > 1 terms. Then assert whether the tid has been removed from the node reference field.

    $taxonomy_index = $this->getTaxonomyIndex();
    $this->assertEquals($term->id(), $taxonomy_index[$node->id()]->tid);
  }

  /**
   * Retrieves the taxonomy index from the database.
   */
  protected function getTaxonomyIndex() {
    return \Drupal::database()->select('taxonomy_index')
      ->fields('taxonomy_index')
      ->execute()
      ->fetchAllAssoc('nid');
  }

}
