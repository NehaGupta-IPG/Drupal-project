<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\StreamWrapper;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\file_test\StreamWrapper\DummyLocalStreamWrapper;
use Drupal\KernelTests\KernelTestBase;

/**
 * @group file
 * @coversDefaultClass \Drupal\Core\StreamWrapper\LocalStream
 */
class LocalStreamWrapperTest extends KernelTestBase {

  /**
   * Tests stream lock operation with an invalid operation.
   *
   * @covers ::stream_lock
   */
  public function testFlockInvalidOp(): void {
    /** @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager */
    $streamWrapperManager = \Drupal::service('stream_wrapper_manager');
    $streamWrapperManager->registerWrapper('dummy', DummyLocalStreamWrapper::class, StreamWrapperInterface::LOCAL_NORMAL);
    $result = file_put_contents("dummy://test.txt", "test");
    $this->assertNotFalse($result);
    $resource = fopen("dummy://test.txt", "rw");
    $this->assertIsResource($resource);
    $this->assertFalse(flock($resource, 9999999999999), 'Invalid operation for stream lock.');
  }

}
