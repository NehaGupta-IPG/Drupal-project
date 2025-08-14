<?php

declare(strict_types=1);

namespace Drupal\Tests\package_manager\Unit;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\package_manager\ComposerRunner;
use Drupal\Tests\UnitTestCase;
use PhpTuf\ComposerStager\API\Finder\Service\ExecutableFinderInterface;
use PhpTuf\ComposerStager\API\Process\Factory\ProcessFactoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Psr\Log\LogLevel;

/**
 * Tests Package Manager's Composer runner service.
 *
 * @internal
 */
#[CoversClass(ComposerRunner::class)]
#[Group('package_manager')]
class ComposerRunnerTest extends UnitTestCase {

  /**
   * Tests that the Composer runner runs Composer through the PHP interpreter.
   */
  public function testRunner(): void {
    $executable_finder = $this->prophesize(ExecutableFinderInterface::class);
    $executable_finder->find('composer')
      ->willReturn('/mock/composer')
      ->shouldBeCalled();

    $process_factory = $this->prophesize(ProcessFactoryInterface::class);
    $process_factory->create(
      // Internally, ComposerRunner uses Symfony's PhpExecutableFinder to locate
      // the PHP interpreter, which should resolve to PHP_BINARY a command-line
      // test environment.
      [PHP_BINARY, '/mock/composer', '--version'],
      NULL,
      Argument::withKey('COMPOSER_HOME'),
    )->shouldBeCalled();

    $file_system = $this->prophesize(FileSystemInterface::class);
    $file_system->getTempDirectory()->shouldBeCalled();
    $file_system->prepareDirectory(Argument::cetera())->shouldBeCalled();

    // The database's idle timeout should be set to whatever the passed timeout
    // is, plus 5 seconds.
    $database = $this->prophesize(Connection::class);
    $database->setIdleTimeout(15)
      ->willThrow(new \Exception("Nope!"))
      ->shouldBeCalled();

    $logger = new TestLogger();

    $runner = new ComposerRunner(
      $executable_finder->reveal(),
      $process_factory->reveal(),
      $file_system->reveal(),
      $this->getConfigFactoryStub([
        'system.site' => [
          'uuid' => 'testing',
        ],
      ]),
      $database->reveal(),
    );
    $runner->setLogger($logger);
    $runner->run(['--version'], timeout: 10);

    // The idle timeout error should have been logged.
    $predicate = static function (array $record): bool {
      return (
        $record['level'] === LogLevel::WARNING &&
        $record['message'] === "Could not adjust the database server's idle timeout before running Composer: @message" &&
        $record['context']['@message'] === 'Nope!'
      );
    };
    $this->assertTrue($logger->hasRecordThatPasses($predicate));
  }

}
