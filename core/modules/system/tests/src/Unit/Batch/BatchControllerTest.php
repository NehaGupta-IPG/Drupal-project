<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Unit\Batch;

use Drupal\Core\Batch\BatchProcessor;
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
    // $batch_processor = $this->createMock(BatchProcessorInterface::class);
    $batch_processor = new BatchProcessor($this->root);
    $controller = new BatchController($this->root, $batch_storage);
    require_once $this->root . '/core/includes/form.inc';
    $this->assertSame('', $controller->batchPageTitle(new Request()));
    // Test no batch loaded from storage and batch loaded from storage cases.
    $current_set = 0;
    $batch = ['sets' => [['title' => 'foobar']], 'current_set' => $current_set];
    // $batch_processor->method('getCurrentBatch')->willReturnReference($batch);
    $batch_storage->method('load')->willReturn(FALSE, $batch);
    $this->assertSame('', $controller->batchPageTitle(new Request(['id' => 1234])));
    // $batch_processor->method('getCurrentSet')->willReturnReference($batch['sets'][$current_set]);
    $this->assertSame('foobar', $controller->batchPageTitle(new Request(['id' => 1234])));
    // Test batch returned by &$batch_processor->getCurrentBatch() call.
    $batch = &$batch_processor->getCurrentBatch();
    $batch['sets'][$current_set]['title'] = 'Updated title';
    $this->assertSame('Updated title', $controller->batchPageTitle(new Request(['id' => 1234])));
  }

}
