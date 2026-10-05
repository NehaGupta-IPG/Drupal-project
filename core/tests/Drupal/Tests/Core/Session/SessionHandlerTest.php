<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Session;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Session\SessionHandler;
use Drupal\Tests\Core\Database\Stub\StubConnection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests session database exception propagation.
 */
#[CoversClass(SessionHandler::class)]
#[Group('Session')]
class SessionHandlerTest extends UnitTestCase {

  /**
   * Tests that unrelated database errors are not swallowed or retried.
   */
  #[TestWith(['doRead', ['missing'], 'queryRange'])]
  #[TestWith(['destroy', ['missing'], 'delete'])]
  #[TestWith(['gc', [3600], 'delete'])]
  public function testDatabaseFailure(string $method, array $arguments, string $query_method): void {
    $connection = $this->getMockBuilder(StubConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['queryRange', 'delete', 'schema'])
      ->getMock();
    $exception = new DatabaseExceptionWrapper('Permission denied');
    $connection->expects($this->once())->method($query_method)->willThrowException($exception);
    $connection->expects($this->never())->method('schema');
    $handler = new SessionHandler(new RequestStack(), $connection, $this->createStub(TimeInterface::class));
    $this->expectExceptionObject($exception);
    $handler->$method(...$arguments);
  }

}
