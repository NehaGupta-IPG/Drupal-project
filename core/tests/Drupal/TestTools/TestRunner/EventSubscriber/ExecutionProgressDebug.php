<?php

declare(strict_types=1);

namespace Drupal\TestTools\TestRunner\EventSubscriber;

use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\TestTools\TestRunner\Configuration as Config;
use Drupal\TestTools\TestRunner\Event\RunnerTestsExecutionFinished;
use Drupal\TestTools\TestRunner\Event\RunnerTestsExecutionPoll;
use Drupal\TestTools\TestRunner\Event\RunnerTestsExecutionStart;
use Drupal\TestTools\TestRunner\Event\TestExecutionFinished;
use Drupal\TestTools\TestRunner\Event\TestExecutionStart;

/**
 * Decorates ExecutionProgress to provide detailed state of test run.
 *
 * @internal
 */
class ExecutionProgressDebug extends ExecutionProgress {

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
   * Subscribes to a status poll of the main runner during test execution.
   *
   * @param \Drupal\TestTools\TestRunner\Event\RunnerTestsExecutionPoll $event
   *   The event.
   */
  public function onRunnerTestsExecutionPoll(RunnerTestsExecutionPoll $event): void {
    $this->io->writeln(sprintf(
      "**POLL  c=%d t=%f Launched=%d Terminated=%d/%d=%d%% Active=%d/%d=%d%% Memory=%s",
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
    ));
  }

  /**
   * Subscribes to a test execution start event.
   *
   * @param \Drupal\TestTools\TestRunner\Event\TestExecutionStart $event
   *   The event.
   */
  public function onTestExecutionStart(TestExecutionStart $event): void {
    $this->io->writeln(sprintf(
      ">>START c=%d t=%f Seq=%d %s",
      $event->cycle,
      $event->time - $this->timeAtTestsExecutionStart,
      $event->sequence,
      $event->testRun->getTestClass(),
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function onTestExecutionFinished(TestExecutionFinished $event): void {
    $this->io->write(sprintf(
      "<<STOP  c=%d t=%f Seq=%d ",
      $event->cycle,
      $event->time - $this->timeAtTestsExecutionStart,
      $event->testRun->sequence,
    ));
    parent::onTestExecutionFinished($event);
  }

}
