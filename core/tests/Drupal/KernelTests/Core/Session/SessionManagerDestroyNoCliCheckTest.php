<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Session;

use Drupal\Core\Session\SessionManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;

/**
 * Tests starting and destroying a session from the CLI.
 */
#[Group('Session')]
#[RunTestsInSeparateProcesses]
class SessionManagerDestroyNoCliCheckTest extends KernelTestBase {

  /**
   * Tests starting and destroying a session from the CLI.
   */
  public function testCallSessionManagerStartAndDestroy(): void {
    $sessionManager = \Drupal::service(SessionStorageFactoryInterface::class)->createStorage(NULL);
    assert($sessionManager instanceof SessionManagerInterface);
    $this->assertFalse($sessionManager->start());
    $this->assertNull($sessionManager->destroy());
  }

}
