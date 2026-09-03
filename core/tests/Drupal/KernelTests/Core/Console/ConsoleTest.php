<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Console;

use Drupal\autowire_test\TestInjection;
use Drupal\console_test\Command\ConsoleDatabaseExceptionCommand;
use Drupal\console_test\Command\ConsoleDatabaseExceptionConsoleMarkupCommand;
use Drupal\console_test\Command\ConsoleDatabaseExceptionNoInfoCommand;
use Drupal\console_test\Command\ConsoleExampleCommand;
use Drupal\console_test\Command\ConsoleExampleConfigureCommand;
use Drupal\console_test\Command\ConsoleExamplePrivateCommand;
use Drupal\Core\Command\DrupalApplication;
use Drupal\Core\DependencyInjection\Compiler\ConsoleCompilerPass;
use Drupal\KernelTests\DrupalApplicationTesterTrait;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tests integration with Symfony Console.
 */
#[Group('Console')]
#[RunTestsInSeparateProcesses]
#[CoversClass(ConsoleCompilerPass::class)]
#[CoversClass(ConsoleExampleCommand::class)]
#[CoversClass(ConsoleDatabaseExceptionCommand::class)]
#[CoversClass(ConsoleDatabaseExceptionConsoleMarkupCommand::class)]
#[CoversClass(ConsoleDatabaseExceptionNoInfoCommand::class)]
#[CoversClass(DrupalApplication::class)]
class ConsoleTest extends KernelTestBase {
  use DrupalApplicationTesterTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'autowire_test',
    'console_test',
  ];

  /**
   * Tests the application wrapped by symfony/runtime.
   */
  public function testApplication(): void {
    $tester = $this->applicationTester();
    $code = $tester->run(['command' => 'example:command']);
    $this->assertEquals(Command::SUCCESS, $code, $tester->getDisplay());
    $this->assertStringContainsString('Dependency injection test: ' . TestInjection::class, $tester->getDisplay());
    $this->assertStringContainsString('Option test: No', $tester->getDisplay());
    $this->assertStringContainsString('Argument test: No', $tester->getDisplay());
    $this->assertStringContainsString('[OK] Done', $tester->getDisplay());

    // Test the command registered via configure() not via the #AsCommand attribute.
    $tester = $this->applicationTester();
    $code = $tester->run(['command' => 'example:command-configured']);
    $this->assertEquals(Command::SUCCESS, $code, $tester->getDisplay());
    $this->assertStringContainsString('Done with configured command.', $tester->getDisplay());

    // Test the example private command.
    $tester = $this->applicationTester();
    $code = $tester->run(['command' => 'example:command-private']);
    $this->assertEquals(Command::SUCCESS, $code, $tester->getDisplay());
    $this->assertStringContainsString('Done with private command.', $tester->getDisplay());
  }

  /**
   * Tests command loader has the discovered commands.
   */
  public function testCommandLoader(): void {
    /** @var \Symfony\Component\Console\CommandLoader\CommandLoaderInterface $commandLoader */
    $commandLoader = \Drupal::service('console.command_loader');
    $command = $commandLoader->get('example:command');
    $this->assertInstanceOf(LazyCommand::class, $command);
    // Ensure the command is registered as a service.
    $this->assertTrue(\Drupal::hasService(ConsoleExampleCommand::class));
    // Ensure the service definition is tagged as a 'console.command'.
    /** @var \Symfony\Component\DependencyInjection\Definition $definition */
    $definition = \Drupal::getContainer()->getDefinition(ConsoleExampleCommand::class);
    $this->assertTrue($definition->hasTag('console.command'), 'ConsoleExampleCommand should be tagged as a console.command');
    $this->assertTrue($definition->isAutowired(), 'ConsoleExampleCommand should be autowired');
  }

  /**
   * Tests command loader has the discovered commands IDs via configure().
   */
  public function testConfigureCommandIds(): void {
    $commandIds = \Drupal::getContainer()->getParameter('console.command.ids');
    $this->assertEquals([
      ConsoleExampleConfigureCommand::class,
      // A public alias is created for this private command.
      'console.command.public_alias.' . ConsoleExamplePrivateCommand::class,
    ], $commandIds);
  }

  /**
   * Tests running a console command.
   *
   * Tests that the command is registered to the container, can be run, returns
   * the correct status code, that autowiring dependencies inside the command
   * works, and verifies the expected output based on the supplied options and
   * arguments.
   */
  public function testConsoleCommand(): void {
    $tester = $this->applicationTester();
    $code = $tester->run(['command' => 'example:command', 'argument-test' => 'Foo', '--option-test' => TRUE], ['capture_stderr_separately' => TRUE]);
    $this->assertEquals(Command::SUCCESS, $code, $tester->getErrorOutput());
    $this->assertStringContainsString('Dependency injection test: ' . TestInjection::class, $tester->getErrorOutput());
    $this->assertStringContainsString('Option test: Yes', $tester->getErrorOutput());
    $this->assertStringContainsString('Argument test: Yes', $tester->getErrorOutput());
    $this->assertStringContainsString('[OK] Done', $tester->getDisplay());
  }

  /**
   * Tests running a console command defined in a subdirectory.
   */
  public function testSubdirectoryCommand(): void {
    $tester = $this->applicationTester();
    $code = $tester->run(['command' => 'example:sub:command'], ['capture_stderr_separately' => TRUE]);
    $this->assertEquals(Command::SUCCESS, $code, $tester->getErrorOutput());
    $this->assertStringContainsString('[OK] Done', $tester->getDisplay());
  }

  /**
   * Tests that a database exception is rendered with its statement dump.
   *
   * @see \Drupal\Core\Command\DrupalApplication::doRenderThrowable()
   */
  public function testDatabaseExceptionRendering(): void {
    $tester = $this->applicationTester();
    $code = $tester->run(['command' => 'example:database-exception']);
    $display = $tester->getDisplay();

    $this->assertEquals(Command::FAILURE, $code, $display);
    $this->assertStringContainsString("Unknown column 'palm_height'", $display);
    // The statement, its placeholders and its options are dumped below the
    // rendered exception.
    $this->assertMatchesRegularExpression('/SQL statement.*SELECT.*FROM.*bananas_are_awesome.*WHERE.*palm_height/s', $display);
    $this->assertMatchesRegularExpression('/Placeholders.*:db_condition_placeholder_0.*180/s', $display);
    $this->assertMatchesRegularExpression('/Options.*allow_delimiter_in_query.*false/s', $display);
    // The compact statement that the message carries for logs is taken off
    // the rendered exception, since the dump repeats it in full.
    $this->assertStringNotContainsString(':db_condition_placeholder_0=180', $display);
  }

  /**
   * Tests that the dump is written without going through the formatter.
   *
   * Placeholder values are user input. The console output formatter would eat
   * a registered tag such as <error>, and throw on a color that does not
   * exist, from inside the exception renderer itself.
   *
   * @see \Drupal\Core\Command\DrupalApplication::doRenderThrowable()
   */
  public function testDatabaseExceptionConsoleMarkupRendering(): void {
    $tester = $this->applicationTester();
    $code = $tester->run(['command' => 'example:database-exception-console-markup'], ['decorated' => FALSE]);
    $display = $tester->getDisplay();

    $this->assertEquals(Command::FAILURE, $code, $display);
    // The renderer did not fail on the unknown color.
    $this->assertStringNotContainsString('nosuchcolor" color', $display);
    // The values are dumped as they were bound.
    $this->assertMatchesRegularExpression('/Placeholders.*<error>eaten\?<\/error> <fg=nosuchcolor>invalid<\/>/s', $display);
  }

  /**
   * Tests that the dump is not dropped at quiet verbosity.
   *
   * The parent renders the exception at VERBOSITY_QUIET, so the dump that
   * belongs to it has to be written at that verbosity too.
   *
   * @see \Drupal\Core\Command\DrupalApplication::doRenderThrowable()
   */
  public function testDatabaseExceptionRenderingAtQuietVerbosity(): void {
    $tester = $this->applicationTester();
    $code = $tester->run(
      ['command' => 'example:database-exception'],
      ['verbosity' => OutputInterface::VERBOSITY_QUIET],
    );
    $display = $tester->getDisplay();

    $this->assertEquals(Command::FAILURE, $code, $display);
    $this->assertStringContainsString("Unknown column 'palm_height'", $display);
    $this->assertMatchesRegularExpression('/SQL statement.*SELECT.*bananas_are_awesome/s', $display);
  }

  /**
   * Tests rendering a database exception that carries no statement information.
   *
   * Database exceptions thrown by Drupal rather than by a driver carry no
   * \Drupal\Core\Database\SqlExecutionInfo, and must still be rendered.
   *
   * @see \Drupal\Core\Command\DrupalApplication::doRenderThrowable()
   */
  public function testDatabaseExceptionWithoutInfoRendering(): void {
    $tester = $this->applicationTester();
    $code = $tester->run(['command' => 'example:database-exception-no-info']);
    $display = $tester->getDisplay();

    $this->assertEquals(Command::FAILURE, $code, $display);
    // The exception itself is reported, and not an error from the renderer.
    $this->assertStringContainsString("Table 'bananas_are_awesome' already exists.", $display);
    $this->assertStringNotContainsString('TypeError', $display);
    // With no information to dump, nothing is added below the exception.
    $this->assertStringNotContainsString('SQL statement', $display);
  }

}
