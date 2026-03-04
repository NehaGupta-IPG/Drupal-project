<?php

namespace Drupal\Core\Utility;

use Drupal\Core\Site\Settings;

/**
 * Basic async primitives wrapping PHP fibers.
 *
 * @template TStart
 * @template TResume
 * @template TReturn
 * @template TSuspend
 */
class Task {

  /**
   * Create an awaitable task from the given callable.
   *
   * @return \Fiber<TStart,TResume,TReturn,TSuspend>|callable
   *   Awaitable task.
   */
  public static function async(callable $task): \Fiber|callable {
    if (Settings::get('async_fibers_enabled', TRUE)) {
      return new \Fiber($task);
    }
    else {
      return $task;
    }
  }

  /**
   * Wait for a task to complete and return its result.
   */
  public static function await(\Fiber|callable $task): mixed {
    if ($task instanceof \Fiber) {
      if (!$task->isStarted()) {
        $task->start();
      }
      while (!$task->isTerminated()) {
        if ($task->isSuspended()) {
          $resume_type = $task->resume();
          if (!$task->isTerminated() && $resume_type !== FiberResumeType::Immediate) {
            usleep(500);
          }
        }
      }
      return $task->getReturn();
    }
    else {
      return $task();
    }
  }

  /**
   * Wait for all tasks to complete and return the results.
   *
   * @param \Fiber|callable $tasks
   *   A list of tasks to wait for.
   */
  public static function awaitAll(array $tasks): array {
    $results = [];
    $iterations = 0;
    while (count($results) !== count($tasks)) {
      foreach ($tasks as $index => $task) {
        if (array_key_exists($index, $results)) {
          continue;
        }
        // @todo This needs to be wrapped into a try-catch block and exceptions
        // need to be propagated to the caller. This is necessary to cover
        // BigPipe requirements.
        if ($task instanceof \Fiber) {
          if (!$task->isStarted()) {
            $task->start();
          }
          elseif ($task->isSuspended()) {
            $task->resume();
          }

          // If the Fiber hasn't terminated by this point, move onto the next
          // task, we'll resume this fiber again when we get back here.
          if (!$task->isTerminated()) {
            // If we've gone through the tasks once already, and they're
            // still not finished, then start to allow code higher up the stack
            // to get on with something else.
            if ($iterations) {
              $fiber = \Fiber::getCurrent();
              if ($fiber !== NULL) {
                $fiber->suspend(FiberResumeType::Immediate);
              }
            }
            continue;
          }
          $results[$index] = $task->getReturn();
        }
        else {
          $results[$index] = $task();
        }
      }
      $iterations++;
    }

    return $results;
  }

}
