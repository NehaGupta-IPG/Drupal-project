<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that exception chaining is preserved through the process pipeline.
 */
#[Group('migrate')]
#[RunTestsInSeparateProcesses]
class MigrateExceptionChainingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['migrate', 'migrate_exception_test'];

  /**
   * Tests that a previous exception is preserved when processing a row.
   */
  public function testExceptionChainingInProcessRow(): void {
    $manager = \Drupal::service('plugin.manager.migration');
    $migration = $manager->createInstance('sample_exception_migration');

    $executable = new MigrateExecutable($migration);
    $source = $migration->getSourcePlugin();
    $source->rewind();
    $row = $source->current();

    $this->expectException(MigrateException::class);

    try {
      $executable->processRow($row);
    }
    catch (MigrateException $e) {
      $this->assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
      $this->assertStringContainsString('The date cannot be created from a format.', $e->getPrevious()->getMessage());
      throw $e;
    }
  }

}
