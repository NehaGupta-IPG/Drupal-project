<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;

// cSpell:ignore indexdef ilike

/**
 * Tests path alias GIST indexes.
 *
 * @coversDefaultClass \Drupal\pgsql\Driver\Database\pgsql\Schema
 *
 * @group path_alias
 * @group pgsql
 */
class PathAliasGistIndexesTest extends DriverSpecificKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['path_alias'];

  /**
   * @covers \Drupal\pgsql\PathAliasStorageSchema::getEntitySchema
   * @covers ::createTableSql
   * @covers ::indexExists
   * @covers ::addIndex
   * @covers ::dropIndex
   * @covers ::introspectIndexSchema
   */
  public function testPathAliasGistIndexes() {
    $connection = Database::getConnection();
    $schema = $connection->schema();

    $this->installEntitySchema('path_alias');
    $this->assertTrue($schema->tableExists('path_alias'));
    $this->assertTrue($schema->tableExists('path_alias_revision'));

    // The GIST indexes should exists on the table path_alias.
    $this->assertTrue($schema->indexExists('path_alias', 'path_alias__alias_gist'));
    $this->assertTrue($schema->indexExists('path_alias', 'path_alias__path_gist'));

    // The GIST indexes should exists on the table path_alias_revision.
    $this->assertTrue($schema->indexExists('path_alias_revision', 'path_alias_revision__alias_gist'));
    $this->assertTrue($schema->indexExists('path_alias_revision', 'path_alias_revision__path_gist'));

    // Test that the method introspectIndexSchema return GIST indexes.
    $introspect_index_schema = new \ReflectionMethod(get_class($schema), 'introspectIndexSchema');
    $introspect_index_schema->setAccessible(TRUE);
    $path_alias_index_schema = $introspect_index_schema->invoke($schema, 'path_alias');
    $this->assertArrayHasKey($connection->getPrefix() . 'path_alias__path_alias__path_gist__idx', $path_alias_index_schema['indexes']);
    $this->assertArrayHasKey($connection->getPrefix() . 'path_alias__path_alias__alias_gist__idx', $path_alias_index_schema['indexes']);

    // Test that a GIST index in Drupal is a GIST index in the database.
    $index_name = $connection->getPrefix() . 'path_alias__path_alias__path_gist__idx';
    $indexdef = $connection->query("SELECT indexdef FROM pg_indexes WHERE indexname = '$index_name'")->fetchField();
    $this->assertStringContainsString('USING gist (path gist_trgm_ops)', $indexdef);

    $connection->insert('path_alias')
      ->fields([
        'path' => '/test-source-case',
        'alias' => '/test-alias',
        'langcode' => 'und',
        'uuid' => \Drupal::service('uuid')->generate(),
        'status' => 1,
      ])
      ->execute();

    // Test that the GIST indexes are used.
    $explain_query_where_path = $connection->query("EXPLAIN SELECT alias FROM {path_alias} WHERE path ILIKE '/test-source-Case'")->fetchAll();
    $this->assertStringContainsString('Index Scan using ' . $connection->getPrefix() . 'path_alias__path_alias__path_gist__gist on ' . $connection->getPrefix() . 'path_alias', $explain_query_where_path[0]->{'QUERY PLAN'});
    $explain_query_where_alias = $connection->query("EXPLAIN SELECT alias FROM {path_alias} WHERE alias ILIKE '/test-Alias'")->fetchAll();
    $this->assertStringContainsString('Index Scan using ' . $connection->getPrefix() . 'path_alias__path_alias__alias_gist__gist on ' . $connection->getPrefix() . 'path_alias', $explain_query_where_alias[0]->{'QUERY PLAN'});

    // Test that a GIST index can be dropped.
    $this->assertTrue($schema->dropIndex('path_alias', 'path_alias__alias_gist'));
    $this->assertFalse($schema->indexExists('path_alias', 'path_alias__alias_gist'));
  }

}
