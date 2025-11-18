<?php

declare(strict_types=1);

namespace Drupal\Tests\sqlite3\Kernel\sqlite3;

use Drupal\KernelTests\Core\Database\SchemaUniquePrefixedKeysIndexTestBase;

/**
 * Tests adding UNIQUE keys to tables.
 *
 * @group Database
 */
class SchemaUniquePrefixedKeysIndexTest extends SchemaUniquePrefixedKeysIndexTestBase {

  /**
   * {@inheritdoc}
   */
  protected string $columnValue = '1234567890 foo';

}
