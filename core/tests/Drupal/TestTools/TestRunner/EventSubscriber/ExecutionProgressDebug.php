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
 * Response subscriber to run-tests.sh events.
 *
 * @internal
 */
final class ExecutionProgressDebug implements EventSubscriberInterface {

  /**
   * The total amount of memory available to PHP, or null if not specified.
   */
  public readonly ?int $totalMemoryAvailable;

  /**
   * The tests to run.
   *
   * @var list<string>
   */
  public private(set) array $testsToRun;

  /**
   * The amount of memory in use when the runner started tests execution.
   */
  public private(set) int $memoryAtTestsExecutionStart;

  /**
   * The amount of memory in use when the runner finished tests execution.
   */
  public private(set) int $memoryAtTestsExecutionFinish;

  /**
   * The time when the runner started tests execution.
   */
  public private(set) float $timeAtTestsExecutionStart;

  /**
   * The time when the runner finished tests execution.
   */
  public private(set) float $timeAtTestsExecutionFinish;

  public function __construct(
    private ConsoleOutput $consoleOutput,
    private Config $config,
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
      RunnerTestsExecutionPoll::class => 'onRunnerTestsExecutionPoll',
      RunnerTestsExecutionFinished::class => 'onRunnerTestsExecutionFinished',
      TestExecutionStart::class => 'onTestExecutionStart',
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
   * Subscribes to a status poll of the main runner during test execution.
   *
   * @param \Drupal\TestTools\TestRunner\Event\RunnerTestsExecutionPoll $event
   *   The event.
   */
  public function onRunnerTestsExecutionPoll(RunnerTestsExecutionPoll $event): void {
    if ($this->config->get('debug-execution')) {
      echo sprintf(
        "**POLL  c=%d t=%f Launched=%d Terminated=%d/%d=%d%% Active=%d/%d=%d%% Memory=%s\n",
        $event->cycle,
        $event->time - $this->timeAtTestsExecutionStart,
        $event->launched,
        $event->terminated,
        count($this->testsToRun),
        (int) round($event->terminated / count($this->testsToRun) * 100),
        $event->active,
        Config::get('concurrency'),
        (int) round($event->active / Config::get('concurrency') * 100),
        $this->totalMemoryAvailable === NULL ? ByteSizeMarkup::create($event->memory) : ByteSizeMarkup::create($event->memory) . '/' . ByteSizeMarkup::create($this->totalMemoryAvailable),
      );
    }
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
   * Subscribes to a test execution start event.
   *
   * @param \Drupal\TestTools\TestRunner\Event\TestExecutionStart $event
   *   The event.
   */
  public function onTestExecutionStart(TestExecutionStart $event): void {
    if ($this->config->get('debug-execution')) {
      echo sprintf(
        ">>START c=%d t=%f Seq=%d %s\n",
        $event->cycle,
        $event->time - $this->timeAtTestsExecutionStart,
        $event->sequence,
        $event->testRun->getTestClass(),
      );
    }
  }

  /**
   * Subscribes to a test execution end event.
   *
   * @param \Drupal\TestTools\TestRunner\Event\TestExecutionFinished $event
   *   The event.
   */
  public function onTestExecutionFinished(TestExecutionFinished $event): void {
    if ($this->config->get('debug-execution')) {
      echo sprintf(
       "<<STOP  c=%d t=%f Seq=%d ",
        $event->cycle,
        $event->time - $this->timeAtTestsExecutionStart,
        $event->testRun->sequence,
      );
      \simpletest_script_reporter_display_summary(
        $event->testRun->getTestClass(),
        $event->result['summaries'][$event->testRun->getTestClass()],
        $event->testRun->duration(),
      );
    }
  }

}
