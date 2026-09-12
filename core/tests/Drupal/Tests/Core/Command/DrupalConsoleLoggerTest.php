<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Command;

use Drupal\Core\Command\DrupalConsoleLogger;
use Drupal\Core\Logger\LogMessageParser;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\InvalidArgumentException;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests DrupalConsoleLogger.
 */
#[Group('command')]
#[CoversClass(DrupalConsoleLogger::class)]
class DrupalConsoleLoggerTest extends UnitTestCase {

  /**
   * Tests the exception thrown by ::log() due to an invalid level.
   */
  public function testLogLevel(): void {
    $logger = new DrupalConsoleLogger(new LogMessageParser());
    $logger->setLogger(new ConsoleLogger(new BufferedOutput()));
    $logger->log(3, 'RFC integer level');
    $logger->log('error', 'PSR string level');
    $this->expectException(InvalidArgumentException::class);
    $logger->log('unknown', 'Invalid string level');
  }

  /**
   * Tests the exception thrown by ::toPsr3() due to an invalid argument.
   */
  public function testToPsr3Exception(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageIs('Invalid log level: -1000');
    DrupalConsoleLogger::toPsr3(-1000);
  }

}
