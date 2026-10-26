<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Batch;

use Drupal\Core\Batch\BatchState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests batch functionality.
 */
#[Group('Batch')]
#[RunTestsInSeparateProcesses]
class BatchKernelTest extends KernelTestBase {

  /**
   * Tests _batch_needs_update().
   */
  public function testLegacyNeedsUpdate(): void {
    require_once $this->root . '/core/includes/batch.inc';
    // Before ever being called, the return value should be FALSE.
    $this->assertFalse(_batch_needs_update());

    // Set the value to TRUE.
    $this->assertTrue(_batch_needs_update(TRUE));
    // Check that without a parameter TRUE is returned.
    $this->assertTrue(_batch_needs_update());

    // Set the value to FALSE.
    $this->assertFalse(_batch_needs_update(FALSE));
    $this->assertFalse(_batch_needs_update());
  }

  /**
   * Tests BatchState::needsUpdate().
   *
   * @see \Drupal\Core\Batch\BatchState::needsUpdate()
   */
  public function testNeedsUpdate(): void {
    $batch_state = \Drupal::service(BatchState::class);
    // Before ever being called, the return value should be FALSE.
    $this->assertFalse($batch_state->needsUpdate());

    // Set the value to TRUE.
    $this->assertTrue($batch_state->needsUpdate(TRUE));
    // Check that without a parameter TRUE is returned.
    $this->assertTrue($batch_state->needsUpdate());

    // Set the value to FALSE.
    $this->assertFalse($batch_state->needsUpdate(FALSE));
    $this->assertFalse($batch_state->needsUpdate());
  }

}
