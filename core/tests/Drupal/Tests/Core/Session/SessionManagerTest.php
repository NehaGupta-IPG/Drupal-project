<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Session;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Session\MetadataBag;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\Core\Session\SessionManager;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Tests \Drupal\Core\Session\SessionManager.
 */
#[CoversClass(SessionManager::class)]
#[Group('Session')]
#[RunTestsInSeparateProcesses]
class SessionManagerTest extends UnitTestCase {

  /**
   * Tests that storage can be reused after a session is destroyed.
   */
  #[DataProvider('destroyProvider')]
  public function testReuseAfterDestroy(bool $save_empty_session): void {
    $stack = new RequestStack();
    $configuration = $this->createStub(SessionConfigurationInterface::class);
    // Disable cookie and cache headers so sessions can start after test output.
    $configuration->method('getOptions')->willReturn([
      'name' => 'SESSION_TEST',
      'use_cookies' => FALSE,
      'cache_limiter' => '',
    ]);
    $configuration->method('hasSession')->willReturnCallback(static fn(Request $request) => $request->cookies->has('SESSION_TEST'));
    $handler = $this->createMock(\SessionHandlerInterface::class);
    $handler->method('open')->willReturn(TRUE);
    $handler->method('close')->willReturn(TRUE);
    $handler->method('read')->willReturn('');
    $handler->method('destroy')->willReturn(TRUE);
    $handler->expects($this->once())->method('write')
      ->with($this->anything(), $this->stringContains('example'))
      ->willReturn(TRUE);
    $storage = $this->getMockBuilder(SessionManager::class)
      ->setConstructorArgs([
        $stack,
        $handler,
        new MetadataBag(new Settings([])),
        $configuration,
        new Time($stack),
      ])
      ->onlyMethods(['isCli'])
      ->getMock();
    // Allow session handling even though PHPUnit runs on the CLI.
    $storage->expects($this->atLeastOnce())->method('isCli')->willReturn(FALSE);
    $session = new Session($storage);

    // Start an empty native session using a cookie with no stored session data.
    $stack->push(Request::create('/', cookies: ['SESSION_TEST' => 'missing']));
    $session->start();
    $this->assertSame(PHP_SESSION_ACTIVE, session_status());
    if ($save_empty_session) {
      $session->save();
    }
    else {
      $storage->destroy();
    }
    $this->assertSame(PHP_SESSION_NONE, session_status());
    $this->assertFalse($session->isStarted());
    $stack->pop();

    // Simulate a new request by clearing $_SESSION and reusing the storage.
    unset($_SESSION);
    $stack->push(Request::create('/'));
    // Without a cookie, initialization is lazy. Saving data starts a native
    // session.
    $session->start();
    $session->set('example', 42);
    $this->assertSame(42, $_SESSION['_sf2_attributes']['example']);
    $session->save();
    $this->assertFalse($session->isStarted());
  }

  /**
   * Provides two ways to destroy a session: directly or by saving it empty.
   */
  public static function destroyProvider(): array {
    return [
      'direct destruction' => [FALSE],
      'obsolete session' => [TRUE],
    ];
  }

}
