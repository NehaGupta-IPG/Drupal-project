<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\jsonapi\IncludeResolver;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\jsonapi\IncludeResolver.
 *
 * @internal
 */
#[CoversClass(IncludeResolver::class)]
#[Group('jsonapi')]
#[RunTestsInSeparateProcesses]
class IncludeResolverTest extends JsonapiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'serialization',
    'system',
    'taxonomy',
    'text',
    'filter',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    Vocabulary::create(['name' => 'Tags', 'vid' => 'tags'])->save();
    $this->createEntityReferenceField(
      'node',
      'article',
      'field_tags',
      'Tags',
      'taxonomy_term',
      'default',
      ['target_bundles' => ['tags']],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );

    Role::create([
      'id' => RoleInterface::ANONYMOUS_ID,
      'label' => 'Anonymous',
      'permissions' => ['access content'],
    ])->save();
  }

  /**
   * Tests that included terms are loaded in one batch across multiple nodes.
   */
  public function testIncludedEntitiesAreLoadedInBulk(): void {
    $expected_uuids = [];
    $node_ids = [];
    $resource_objects = [];
    $resource_type = $this->container->get('jsonapi.resource_type.repository')->get('node', 'article');
    for ($i = 0; $i < 5; $i++) {
      $tags = [];
      for ($j = 0; $j < 2; $j++) {
        $tag = Term::create(['vid' => 'tags', 'name' => "Tag $i-$j"]);
        $tag->save();
        $tags[] = $tag;
        $expected_uuids[] = $tag->uuid();
      }
      $node = Node::create([
        'type' => 'article',
        'title' => "Article $i",
        'field_tags' => $tags,
      ]);
      $node->save();
      $node_ids[] = $node->id();
    }

    // Reload the nodes so their reference field items no longer hold term
    // objects. Clear the static and persistent term caches so the initial
    // term loads query the database.
    $entity_type_manager = $this->container->get('entity_type.manager');
    $node_storage = $entity_type_manager->getStorage('node');
    $node_storage->resetCache();
    $entity_type_manager->getStorage('taxonomy_term')->resetCache();
    foreach ($node_storage->loadMultiple($node_ids) as $node) {
      $resource_objects[] = ResourceObject::createFromEntity($resource_type, $node);
    }

    Database::startLog('include_resolver');
    $includes = $this->container->get('jsonapi.include_resolver')
      ->resolve(new ResourceObjectData($resource_objects), 'field_tags');
    $queries = Database::getLog('include_resolver');

    $included_uuids = array_map(fn ($resource_object) => $resource_object->getId(), $includes->toArray());
    sort($expected_uuids);
    sort($included_uuids);
    $this->assertSame($expected_uuids, $included_uuids);

    // With empty term caches, a single base table query means that all ten
    // terms were loaded together.
    $term_base_table_queries = array_filter($queries, fn (array $query) => preg_match('/FROM\s+\S*taxonomy_term_data\S*\s+\S*base\b/i', $query['query']));
    $this->assertCount(1, $term_base_table_queries);
  }

}
