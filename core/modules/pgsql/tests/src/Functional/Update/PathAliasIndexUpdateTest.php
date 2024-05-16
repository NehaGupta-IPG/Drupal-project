<?php

namespace Drupal\Tests\pgsql\Functional\Update;

use Drupal\Core\Database\Database;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests that the indexes are created during the update.
 *
 * @group pgsql
 * @group Update
 */
class PathAliasIndexUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-9.4.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests that the indexes are created during the update.
   */
  public function testPathAliasIndexUpdate() {
    $connection = Database::getConnection();
    if ($connection->driver() !== 'pgsql') {
      $this->markTestSkipped('This test only works with the pgsql driver');
    }
    $schema = $connection->schema();

    // The path_alias table should exist and the indexes should not exist.
    $this->assertTrue($schema->tableExists('path_alias'));
    $this->assertFalse($schema->indexExists('path_alias', 'path_alias__alias_gist'));
    $this->assertFalse($schema->indexExists('path_alias', 'path_alias__path_gist'));

    // The path_alias_revision table should exist and the indexes should not
    // exist.
    $this->assertTrue($schema->tableExists('path_alias_revision'));
    $this->assertFalse($schema->indexExists('path_alias_revision', 'path_alias_revision__alias_gist'));
    $this->assertFalse($schema->indexExists('path_alias_revision', 'path_alias_revision__path_gist'));

    $this->runUpdates();

    // The indexes should exists on the table path_alias.
    $this->assertTrue($schema->indexExists('path_alias', 'path_alias__alias_gist'));
    $this->assertTrue($schema->indexExists('path_alias', 'path_alias__path_gist'));

    // The indexes should exists on the table path_alias_revision.
    $this->assertTrue($schema->indexExists('path_alias_revision', 'path_alias_revision__alias_gist'));
    $this->assertTrue($schema->indexExists('path_alias_revision', 'path_alias_revision__path_gist'));
  }

}
