<?php

// phpcs:ignoreFile

declare(strict_types=1);

use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
class SqlExceptionDumpTest extends KernelTestBase {

  public function testSqlException(): void {
    Database::getConnection()->select('bananas_are_awesome', 'b')
      ->fields('b')
      ->condition('palm_height', 180, '>')
      ->execute();
  }

}
