<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Kernel\mongodb;

use Drupal\KernelTests\Core\Database\TemporaryQueryTestBase;

/**
 * Tests the temporary query functionality.
 *
 * @group Database
 */
class TemporaryQueryTest extends TemporaryQueryTestBase {

  /**
   * Confirms that temporary tables work.
   */
  public function testTemporaryQuery(): void {
    $this->markTestSkipped('The MongoDB database driver does not support temporary tables.');
  }

}
