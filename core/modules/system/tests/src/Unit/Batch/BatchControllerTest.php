<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Unit\Batch;

use Drupal\Core\Batch\BatchState;
use Drupal\Core\Batch\BatchStorageInterface;
use Drupal\system\Controller\BatchController;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the batch controller class.
 */
#[CoversClass(BatchController::class)]
#[Group('system')]
#[RunTestsInSeparateProcesses]
class BatchControllerTest extends UnitTestCase {

  /**
   * Tests title callback.
   */
  public function testBatchPageTitle(): void {
    $batch_storage = $this->createStub(BatchStorageInterface::class);
    $batch_state = new BatchState($this->root);
    $controller = new BatchController($this->root, $batch_storage);
    $this->assertSame('', $controller->batchPageTitle(new Request()));
    // Test no batch loaded from storage and batch loaded from storage cases.
    $current_set = 0;
    $batch = ['sets' => [['title' => 'foobar']], 'current_set' => $current_set];
    $batch_storage->method('load')->willReturnOnConsecutiveCalls(FALSE, $batch);
    $this->assertSame('', $controller->batchPageTitle(new Request(['id' => 1234])));
    // Clear the global batch state.
    $global_batch = &$batch_state->get();
    $global_batch = [];
    $this->assertSame('foobar', $controller->batchPageTitle(new Request(['id' => 1234])));
    // Verify BatchState can access the batch loaded by the controller.
    $batch_ref = &$batch_state->get();
    $this->assertSame($batch['sets'][$current_set]['title'], $batch_ref['sets'][$current_set]['title']);
    $this->assertSame($batch['sets'][$current_set], $batch_state->getCurrentSet());
    // Test batch returned by &$batch_state->get() call.
    $batch_ref['sets'][$current_set]['title'] = 'Updated title';
    $this->assertSame('Updated title', $controller->batchPageTitle(new Request(['id' => 1234])));
  }

}
