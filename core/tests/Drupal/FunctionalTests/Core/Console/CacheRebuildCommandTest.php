<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Core\Console;

use Composer\Autoload\ClassLoader;
use Drupal\Core\Command\CacheRebuildCommand;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the cache:rebuild command.
 */
#[Group('Console')]
#[RunTestsInSeparateProcesses]
#[CoversClass(CacheRebuildCommand::class)]
class CacheRebuildCommandTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the cache:rebuild command.
   */
  public function testConsoleCommand(): void {
    $errorHandler = get_error_handler();
    $command = new CacheRebuildCommand(new ClassLoader());
    $tester = new CommandTester($command);
    $code = $tester->execute([]);
    $this->assertStringContainsString('All caches have been rebuilt.', $tester->getDisplay());
    $this->assertEquals(Command::SUCCESS, $code);
    set_error_handler($errorHandler);
  }

}
