<?php
// @todo remove the ignore directive once PHPCS will support hooked properties.
// phpcs:ignoreFile

declare(strict_types=1);

namespace Drupal\TestTools\TestRunner\EventSubscriber;

use Drupal\Component\Utility\Bytes;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\Test\TestDatabase;
use Drupal\Core\Test\TestRun;
use Drupal\TestTools\TestRunner\Configuration as Config;
use Drupal\TestTools\TestRunner\Event\RunnerTestsExecutionFinished;
use Drupal\TestTools\TestRunner\Event\RunnerTestsExecutionPoll;
use Drupal\TestTools\TestRunner\Event\RunnerTestsExecutionStart;
use Drupal\TestTools\TestRunner\Event\TestExecutionFinished;
use Drupal\TestTools\TestRunner\Event\TestExecutionStart;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Response subscriber to provide test runner execution progress.
 *
 * @internal
 */
class ExecutionProgress implements EventSubscriberInterface {

  /**
   * The total amount of memory available to PHP, or null if not specified.
   */
  public readonly ?int $totalMemoryAvailable;

  /**
   * The tests to run.
   *
   * @var list<string>
   */
  public protected(set) array $testsToRun;

  /**
   * The amount of memory in use when the runner started tests execution.
   */
  public protected(set) int $memoryAtTestsExecutionStart;

  /**
   * The amount of memory in use when the runner finished tests execution.
   */
  public protected(set) int $memoryAtTestsExecutionFinish;

  /**
   * The time when the runner started tests execution.
   */
  public protected(set) float $timeAtTestsExecutionStart;

  /**
   * The time when the runner finished tests execution.
   */
  public protected(set) float $timeAtTestsExecutionFinish;

  public function __construct(
    protected ConsoleOutput $io,
    protected Config $config,
  ) {
    $tot_memory = NULL;
    $ini_memory = ini_get('memory_limit');
    if ($ini_memory && $ini_memory != -1) {
      $tot_memory = Bytes::toNumber($ini_memory);
    }
    $this->totalMemoryAvailable = $tot_memory === NULL ? NULL : (int) $tot_memory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RunnerTestsExecutionStart::class => 'onRunnerTestsExecutionStart',
      RunnerTestsExecutionFinished::class => 'onRunnerTestsExecutionFinished',
      TestExecutionFinished::class => 'onTestExecutionFinished',
    ];
  }

  /**
   * Subscribes to a runner tests execution start event.
   *
   * @param \Drupal\TestTools\TestRunner\Event\RunnerTestsExecutionStart $event
   *   The event.
   */
  public function onRunnerTestsExecutionStart(RunnerTestsExecutionStart $event): void {
    $this->timeAtTestsExecutionStart = $event->time;
    $this->memoryAtTestsExecutionStart = $event->memory;
    $this->testsToRun = $event->testsToRun;
  }

  /**
   * Subscribes to a runner tests execution finished event.
   *
   * @param \Drupal\TestTools\TestRunner\Event\RunnerTestsExecutionFinished $event
   *   The event.
   */
  public function onRunnerTestsExecutionFinished(RunnerTestsExecutionFinished $event): void {
    $this->timeAtTestsExecutionFinish = $event->time;
    $this->memoryAtTestsExecutionFinish = $event->memory;
  }

  /**
   * Subscribes to a test execution end event.
   *
   * @param \Drupal\TestTools\TestRunner\Event\TestExecutionFinished $event
   *   The event.
   */
  public function onTestExecutionFinished(TestExecutionFinished $event): void {
    $this->printSummary(
      $event->testRun->getTestClass(),
      $event->result['summaries'][$event->testRun->getTestClass()] ?? [],
      $event->testRun->duration(),
    );
    if ($event->result['error_output']) {
      $this->io->writeln('<error>ERROR: ' . implode("\n", $event->result['error_output']) . '</>');
    }
    if ($event->result['status'] && !in_array($event->result['status'], [\SIMPLETEST_SCRIPT_EXIT_FAILURE, \SIMPLETEST_SCRIPT_EXIT_ERROR])) {
      $message = 'FATAL ' . $event->testRun->getTestClass() . ': test runner returned an unexpected error code (' . $event->result['status'] . ').';
      $this->io->writeln('<error>' . $message . '</>');
      if ($this->config->get('die-on-fail')) {
        $this->io->writeln('Test database and files kept and test exited immediately on fail so should be reproducible if you change settings.php to use the database prefix ' . $event->testRun->getDatabasePrefix() . ' and config directories in ' . $event->result['test_directory']);
      }
    }
  }

  /**
   * Displays the assertion result summary for a single test class.
   *
   * @param string $class
   *   The test class name that was run.
   * @param array $results
   *   The assertion results using #pass, #fail, #exception, #debug array keys.
   * @param float|null $duration
   *   The time taken for the test to complete.
   */
  private function printSummary(string $class, array $results, ?float $duration = NULL): void {
    // Output all test results vertically aligned.
    $summary = [str_pad((string) $results['#pass'], 4, " ", STR_PAD_LEFT) . ' passed'];
    if ($results['#fail']) {
      $summary[] = $results['#fail'] . ' failed';
    }
    if ($results['#error']) {
      $summary[] = $results['#error'] . ' errored';
    }
    if ($results['#skipped']) {
      $summary[] = $results['#skipped'] . ' skipped';
    }
    if ($results['#exception']) {
      $summary[] = $results['#exception'] . ' exception(s)';
    }
    if ($results['#debug']) {
      $summary[] = $results['#debug'] . ' log(s)';
    }

    if ($results['#cli_fail']) {
      $summary[] = 'exit code ' . $results['#exit_code'];
    }

    // The key $results['#time'] holds the sum of the tests execution times,
    // without taking into account the process spawning time and the setup
    // times of the tests themselves. So for reporting to be consistent with
    // PHPUnit CLI reported execution time, we report here the overall time of
    // execution of the spawned process.
    $time = sprintf('%8.3fs', $duration);

    $output = vsprintf('%s %s %s', [$time, \trim_with_ellipsis($class, 70, STR_PAD_LEFT), implode(', ', $summary)]);
    $status = ($results['#fail'] || $results['#cli_fail'] || $results['#exception'] || $results['#error'] ? 'fail' : 'pass');
    $tag = \simpletest_script_color_console_tag($status);
    $this->io->writeln($tag ? $tag . $output ."</>" : $output);
  }

}
