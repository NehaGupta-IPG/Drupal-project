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
    $batch_processor = $this->createMock(BatchProcessor::class);
    $controller = new BatchController($batch_storage, $batch_processor);
    require_once $this->root . '/core/includes/form.inc';
    $this->assertSame('', $controller->batchPageTitle(new Request()));
    // Test no batch loaded from storage and batch loaded from storage cases.
    $batch = ['sets' => [['title' => 'foobar']], 'current_set' => 0];
    $batch_storage->method('load')->willReturn(FALSE, $batch);
    $this->assertSame('', $controller->batchPageTitle(new Request(['id' => 1234])));
    $batch_processor->expects($this->exactly(2))->method('currentSet')
      ->willReturn(['title' => 'foobar'], ['title' => 'Updated title']);
    $this->assertSame('foobar', $controller->batchPageTitle(new Request(['id' => 1234])));
    // Test batch returned by &batch_get() call.
    $batch = &batch_get();
    $batch['sets']['0']['title'] = 'Updated title';
    $this->assertSame('Updated title', $controller->batchPageTitle(new Request(['id' => 1234])));
  }

}
