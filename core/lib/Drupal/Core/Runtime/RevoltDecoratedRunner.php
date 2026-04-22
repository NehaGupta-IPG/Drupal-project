<?php

namespace Drupal\Core\Runtime;

use Revolt\EventLoop;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * Decorator to wrap a Symfony Runtime Runner in a Revolt Event Loop task.
 *
 * Applications should only have a single invocation of `EventLoop::run()`.
 * This Runner class will decorate any other Runner (inner) and ensure that all
 * work done by the inner runner is executed as a single suspendable task on
 * the event loop.
 *
 * Once that task is scheduled, this runner will execute the event loop and run
 * all scheduled work to completion. This ensures that if the inner task sets up
 * any background tasks (e.g. a post-request cron run) that these will run to
 * completion.
 */
class RevoltDecoratedRunner implements RunnerInterface {

  /**
   * @param \Symfony\Component\Runtime\RunnerInterface $inner
   */
  public function __construct(
    protected RunnerInterface $inner,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function run(): int {
    // Schedule the inner run as a deferred task on the event loop. Since this
    // is the only task we schedule, it will be executed as soon as
    // EventLoop::run() is called.
    // We store the return value in a variable passed by reference so that we
    // can return it at the end of our decorating run function and transparently
    // propagate it. The deferred task is guaranteed by Revolt to have completed
    // when EventLoop::run has executed so we can be sure that we return an int.
    $ret = 0;
    EventLoop::defer(function () use (&$ret) {
      $ret = $this->inner->run();
    });
    EventLoop::run();
    return $ret;
  }

}
