<?php

declare(strict_types=1);

namespace Drupal\Tests\package_manager\Unit;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\Core\Database\Connection;
use Drupal\package_manager\RsyncRunner;
use Drupal\Tests\UnitTestCase;
use PhpTuf\ComposerStager\API\Process\Service\RsyncProcessRunnerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LogLevel;

/**
 * Tests Package Manager's rsync runner service.
 *
 * @internal
 */
#[CoversClass(RsyncRunner::class)]
#[Group('package_manager')]
class RsyncRunnerTest extends UnitTestCase {

  /**
   * Tests that the rsync runner changes the database idle timeout.
   */
  public function testRunner(): void {
    $database = $this->prophesize(Connection::class);
    $database->setIdleTimeout(15)
      ->willThrow(new \Exception("Nope!"))
      ->shouldBeCalled();

    $runner = new RsyncRunner(
      $this->prophesize(RsyncProcessRunnerInterface::class)->reveal(),
      $database->reveal(),
    );
    $logger = new TestLogger();
    $runner->setLogger($logger);
    $runner->run(['--version'], timeout: 10);

    // The idle timeout error should have been logged.
    $predicate = static function (array $record): bool {
      return (
        $record['level'] === LogLevel::WARNING &&
        $record['message'] === "Could not adjust the database server's idle timeout before running rsync: @message" &&
        $record['context']['@message'] === 'Nope!'
      );
    };
    $this->assertTrue($logger->hasRecordThatPasses($predicate));
  }

}
