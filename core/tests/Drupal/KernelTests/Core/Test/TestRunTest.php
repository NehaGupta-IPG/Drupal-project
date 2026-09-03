<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Test;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Test\JUnitConverter;
use Drupal\Core\Test\PhpUnitTestRunner;
use Drupal\Core\Test\SimpletestTestRunResultsStorage;
use Drupal\Core\Test\TestRun;
use Drupal\Core\Test\TestRunResultsStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\TestTools\TestRunner\MemoryTestRunResultsStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Process\Process;

/**
 * Tests Drupal\Core\Test\TestRun.
 */
#[CoversClass(TestRun::class)]
#[Group('Test')]
#[RunTestsInSeparateProcesses]
class TestRunTest extends KernelTestBase {

  /**
   * The database connection for testing.
   *
   * NOTE: this is the connection to the fixture database to allow testing the
   * storage class, NOT the database where actual tests results are stored.
   */
  protected Connection $connection;

  /**
   * The test run results storage.
   */
  protected TestRunResultsStorageInterface $testRunResultsStorage;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->connection = Database::getConnection();
    $this->testRunResultsStorage = new SimpletestTestRunResultsStorage($this->connection);
    $this->testRunResultsStorage->buildTestingResultsEnvironment(FALSE);
  }

  /**
   * Tests create and get.
   */
  public function testCreateAndGet(): void {
    // Test ::createNew.
    $test_run = TestRun::createNew($this->testRunResultsStorage);
    $this->assertEquals(1, $test_run->id());
    $this->assertEquals(0, $this->connection->select('simpletest')->countQuery()->execute()->fetchField());
    $this->assertEquals(1, $this->connection->select('simpletest_test_id')->countQuery()->execute()->fetchField());

    $test_run->setDatabasePrefix('oddity1234');
    $this->assertEquals('oddity1234', $test_run->getDatabasePrefix());
    $this->assertEquals('oddity1234', $this->connection->select('simpletest_test_id', 's')->fields('s', ['last_prefix'])->execute()->fetchField());

    $this->assertEquals(1, $test_run->insertLogEntry($this->getTestLogEntry('Test\GroundControl')));
    $this->assertEquals('oddity1234', $test_run->getDatabasePrefix());
    $this->assertEquals('Test\GroundControl', $test_run->getTestClass());
    $this->assertEquals(1, $this->connection->select('simpletest')->countQuery()->execute()->fetchField());
    $this->assertEquals(1, $this->connection->select('simpletest_test_id')->countQuery()->execute()->fetchField());

    // Explicitly void the $test_run variable.
    $test_run = NULL;

    // Test ::get.
    $test_run = TestRun::get($this->testRunResultsStorage, 1);
    $this->assertEquals(1, $test_run->id());
    $this->assertEquals('oddity1234', $test_run->getDatabasePrefix());
    $this->assertEquals('Test\GroundControl', $test_run->getTestClass());
  }

  /**
   * Tests create and remove.
   */
  public function testCreateAndRemove(): void {
    $test_run_1 = TestRun::createNew($this->testRunResultsStorage);
    $test_run_1->setDatabasePrefix('oddity1234');
    $test_run_1->insertLogEntry($this->getTestLogEntry('Test\GroundControl'));
    $this->assertEquals(1, $test_run_1->id());
    $this->assertEquals(1, $this->connection->select('simpletest')->countQuery()->execute()->fetchField());
    $this->assertEquals(1, $this->connection->select('simpletest_test_id')->countQuery()->execute()->fetchField());

    $test_run_2 = TestRun::createNew($this->testRunResultsStorage);
    $test_run_2->setDatabasePrefix('oddity5678');
    $test_run_2->insertLogEntry($this->getTestLogEntry('Test\PlanetEarth'));
    $this->assertEquals(2, $test_run_2->id());
    $this->assertEquals(2, $this->connection->select('simpletest')->countQuery()->execute()->fetchField());
    $this->assertEquals(2, $this->connection->select('simpletest_test_id')->countQuery()->execute()->fetchField());

    $this->assertEquals(1, $test_run_1->removeResults());
    $this->assertEquals(1, $this->connection->select('simpletest')->countQuery()->execute()->fetchField());
    $this->assertEquals(1, $this->connection->select('simpletest_test_id')->countQuery()->execute()->fetchField());
  }

  /**
   * Tests get log entries by test class.
   */
  public function testGetLogEntriesByTestClass(): void {
    $test_run = TestRun::createNew($this->testRunResultsStorage);
    $test_run->setDatabasePrefix('oddity1234');
    $this->assertEquals(1, $test_run->insertLogEntry($this->getTestLogEntry('Test\PlanetEarth')));
    $this->assertEquals(2, $test_run->insertLogEntry($this->getTestLogEntry('Test\GroundControl')));
    $this->assertEquals([
      0 => (object) [
        'message_id' => '2',
        'test_id' => '1',
        'test_class' => 'Test\GroundControl',
        'status' => 'pass',
        'message' => 'Major Tom',
        'message_group' => 'other',
        'function' => 'Unknown',
        'line' => '0',
        'file' => 'Unknown',
        'time' => '0',
        'exit_code' => '0',
      ],
      1 => (object) [
        'message_id' => '1',
        'test_id' => '1',
        'test_class' => 'Test\PlanetEarth',
        'status' => 'pass',
        'message' => 'Major Tom',
        'message_group' => 'other',
        'function' => 'Unknown',
        'line' => '0',
        'file' => 'Unknown',
        'time' => '0',
        'exit_code' => '0',
      ],
    ], $test_run->getLogEntriesByTestClass());
    $this->assertEquals('oddity1234', $test_run->getDatabasePrefix());
    $this->assertEquals('Test\GroundControl', $test_run->getTestClass());
  }

  /**
   * Tests process php error log file.
   */
  public function testProcessPhpErrorLogFile(): void {
    $test_run = TestRun::createNew($this->testRunResultsStorage);
    $test_run->setDatabasePrefix('oddity1234');
    $test_run->processPhpErrorLogFile('core/tests/fixtures/test-error.log', 'Test\PlanetEarth');
    $this->assertEquals([
      0 => (object) [
        'message_id' => '1',
        'test_id' => '1',
        'test_class' => 'Test\PlanetEarth',
        'status' => 'fail',
        'message' => "Argument 1 passed to Drupal\FunctionalTests\Bootstrap\ErrorContainer::Drupal\FunctionalTests\Bootstrap\{closure}() must be an instance of Drupal\FunctionalTests\Bootstrap\ErrorContainer, int given, called",
        'message_group' => 'TypeError',
        'function' => 'Unknown',
        'line' => '18',
        'file' => '/var/www/core/tests/Drupal/FunctionalTests/Bootstrap/ErrorContainer.php on line 20 in /var/www/core/tests/Drupal/FunctionalTests/Bootstrap/ErrorContainer.php',
        'time' => '0',
        'exit_code' => '0',
      ],
      1 => (object) [
        'message_id' => '2',
        'test_id' => '1',
        'test_class' => 'Test\PlanetEarth',
        'status' => 'fail',
        'message' => "#1 /var/www/core/lib/Drupal/Core/DrupalKernel.php(1396): Drupal\FunctionalTests\Bootstrap\ErrorContainer->get('http_kernel')\n",
        'message_group' => 'Fatal error',
        'function' => 'Unknown',
        'line' => '0',
        'file' => 'Unknown',
        'time' => '0',
        'exit_code' => '0',
      ],
      2 => (object) [
        'message_id' => '3',
        'test_id' => '1',
        'test_class' => 'Test\PlanetEarth',
        'status' => 'fail',
        'message' => "#2 /var/www/core/lib/Drupal/Core/DrupalKernel.php(693): Drupal\Core\DrupalKernel->getHttpKernel()\n",
        'message_group' => 'Fatal error',
        'function' => 'Unknown',
        'line' => '0',
        'file' => 'Unknown',
        'time' => '0',
        'exit_code' => '0',
      ],
      3 => (object) [
        'message_id' => '4',
        'test_id' => '1',
        'test_class' => 'Test\PlanetEarth',
        'status' => 'fail',
        'message' => "#3 /var/www/index.php(19): Drupal\Core\DrupalKernel->handle(Object(Symfony\Component\HttpFoundation\Request))\n",
        'message_group' => 'Fatal error',
        'function' => 'Unknown',
        'line' => '0',
        'file' => 'Unknown',
        'time' => '0',
        'exit_code' => '0',
      ],
      4 => (object) [
        'message_id' => '5',
        'test_id' => '1',
        'test_class' => 'Test\PlanetEarth',
        'status' => 'fail',
        'message' => "#4 {main}\n",
        'message_group' => 'Fatal error',
        'function' => 'Unknown',
        'line' => '0',
        'file' => 'Unknown',
        'time' => '0',
        'exit_code' => '0',
      ],
      5 => (object) [
        'message_id' => '6',
        'test_id' => '1',
        'test_class' => 'Test\PlanetEarth',
        'status' => 'fail',
        'message' => "Thrown exception during Container::get",
        'message_group' => 'Exception',
        'function' => 'Unknown',
        'line' => '17',
        'file' => '/var/www/core/tests/Drupal/FunctionalTests/Bootstrap/ExceptionContainer.php',
        'time' => '0',
        'exit_code' => '0',
      ],
      6 => (object) [
        'message_id' => '7',
        'test_id' => '1',
        'test_class' => 'Test\PlanetEarth',
        'status' => 'fail',
        'message' => "#1 /var/www/core/lib/Drupal/Core/DrupalKernel.php(693): Drupal\Core\DrupalKernel->getHttpKernel()\n",
        'message_group' => 'Fatal error',
        'function' => 'Unknown',
        'line' => '0',
        'file' => 'Unknown',
        'time' => '0',
        'exit_code' => '0',
      ],
      7 => (object) [
        'message_id' => '8',
        'test_id' => '1',
        'test_class' => 'Test\PlanetEarth',
        'status' => 'fail',
        'message' => "#2 /var/www/index.php(19): Drupal\Core\DrupalKernel->handle(Object(Symfony\Component\HttpFoundation\Request))\n",
        'message_group' => 'Fatal error',
        'function' => 'Unknown',
        'line' => '0',
        'file' => 'Unknown',
        'time' => '0',
        'exit_code' => '0',
      ],
      8 => (object) [
        'message_id' => '9',
        'test_id' => '1',
        'test_class' => 'Test\PlanetEarth',
        'status' => 'fail',
        'message' => "#3 {main}\n",
        'message_group' => 'Fatal error',
        'function' => 'Unknown',
        'line' => '0',
        'file' => 'Unknown',
        'time' => '0',
        'exit_code' => '0',
      ],
    ], $test_run->getLogEntriesByTestClass());
  }

  /**
   * Tests process php unit results.
   */
  public function testProcessPhpUnitResults(): void {
    $phpunit_error_xml = __DIR__ . '/../../../../fixtures/phpunit_error.xml';
    $res = JUnitConverter::xmlToRows(1, $phpunit_error_xml);

    $runner = PhpUnitTestRunner::create(\Drupal::getContainer());
    $test_run = TestRun::createNew($this->testRunResultsStorage);
    $runner->processPhpUnitResults($test_run, $res);

    $this->assertEquals(4, $this->connection->select('simpletest')->countQuery()->execute()->fetchField());
  }

  /**
   * Returns a sample test run log entry.
   *
   * @param string $test_class
   *   The test class.
   *
   * @return string[]
   *   An array with the elements to be logged.
   */
  protected function getTestLogEntry(string $test_class): array {
    return [
      'test_class' => $test_class,
      'status' => 'pass',
      'message' => 'Major Tom',
      'message_group' => 'other',
    ];
  }

  /**
   * Tests SQL exception dumps the execution information in PHPUnit.
   */
  public function testSqlStatementExceptionDump(): void {
    $process = $this->runTestFixture('SqlExceptionDumpTest.php');
    $output = $process->getOutput();
    $message = $output . $process->getErrorOutput();
    $this->assertMatchesRegularExpression('/SQL statement.*SELECT.*FROM.*bananas_are_awesome.*WHERE.*palm_height.*\>.*:db_condition_placeholder_0/s', $output, $message);
    $this->assertMatchesRegularExpression('/Placeholders.*:db_condition_placeholder_0.*180/s', $output, $message);
    $this->assertMatchesRegularExpression('/Options.*allow_delimiter_in_query.*false/s', $output, $message);
    // The fixture is expected to error, so the process must not report success.
    $this->assertNotSame(0, $process->getExitCode(), $message);
  }

  /**
   * Tests reporting a SQL exception that carries no execution information.
   *
   * Database exceptions raised by Drupal instead of by a database driver carry
   * no \Drupal\Core\Database\SqlExecutionInfo, and must still be reported.
   *
   * @see \Drupal\Tests\DrupalTestCaseTrait::transformException()
   */
  public function testSqlStatementExceptionDumpWithoutInfo(): void {
    $process = $this->runTestFixture('SqlExceptionNoInfoDumpTest.php');
    $output = $process->getOutput();
    $message = $output . $process->getErrorOutput();
    // The exception itself is reported, and not an error from the reporting.
    $this->assertStringContainsString("Table 'bananas_are_awesome' already exists.", $output, $message);
    $this->assertStringNotContainsString('TypeError', $output, $message);
    // With no information to dump, nothing is added to the exception message.
    $this->assertStringNotContainsString('SQL statement', $output, $message);
    // A failure while reporting the exception can make PHPUnit report the
    // failing test as successful, so check the process failed.
    $this->assertNotSame(0, $process->getExitCode(), $message);
  }

  /**
   * Runs a test fixture file in a PHPUnit subprocess.
   *
   * @param string $fixture
   *   The file name of a test fixture in core/tests/fixtures/TestTools.
   *
   * @return \Symfony\Component\Process\Process
   *   The terminated PHPUnit process.
   */
  protected function runTestFixture(string $fixture): Process {
    $runner = PhpUnitTestRunner::create(\Drupal::getContainer())
      ->setConfigurationFilePath($this->root . '/core');
    $process = $runner->startPhpUnitOnSingleTestFile(
      TestRun::createNew(new MemoryTestRunResultsStorage()),
      $this->root . '/core/tests/fixtures/TestTools/' . $fixture,
      colors: FALSE,
      suppressDeprecations: FALSE,
    );
    $process->wait();
    // No output at all means the fixture was never run.
    $this->assertNotSame('', $process->getOutput(), $process->getErrorOutput());
    return $process;
  }

}
