<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Functional;

use Drupal\Core\Database\Database;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests update functions for the Locale module.
 */
#[Group('locale')]
#[RunTestsInSeparateProcesses]
class UpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../system/tests/fixtures/update/drupal-11.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests that, after update 11401, the 'hash' column exists in {locale_file}.
   */
  public function testAddHashColumn(): void {
    $schema = Database::getConnection()->schema();
    $this->assertFalse($schema->fieldExists('locale_file', 'hash'));
    $this->runUpdates();
    $this->assertTrue($schema->fieldExists('locale_file', 'hash'));
  }

}
