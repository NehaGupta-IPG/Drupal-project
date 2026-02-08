<?php

namespace Drupal\Tests\migrate\Unit;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutable;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the MigrateException class.
 */
#[CoversClass(MigrateExecutable::class)]
#[Group('migrate')]
class MigrateExceptionTest extends UnitTestCase {

  /**
   * Ensures code and previous are passed to the parent constructor.
   */
  public function testCodeAndPrevious(): void {
    $previous = new \Exception('A previous message');
    $exception = new MigrateException('Migration failed', 1234, $previous);

    $this->assertSame($previous, $exception->getPrevious());
    $this->assertSame('A previous message', $exception->getPrevious()->getMessage());
    $this->assertSame(1234, $exception->getCode());
    $this->assertSame('Migration failed', $exception->getMessage());
  }

}
