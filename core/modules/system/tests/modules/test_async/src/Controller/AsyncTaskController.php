<?php

declare(strict_types=1);

namespace Drupal\test_async\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\State\StateInterface;
use Revolt\EventLoop;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller to test async tasks beyond request.
 */
class AsyncTaskController extends ControllerBase {

  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Schedules a task that happens after the request is completed.
   *
   * Will set the 'async' key in Drupal state to true after the call stack has
   * emptied. That code only runs if some code in the application runs
   * <code>EventLoop::run()</code>.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  #[Route(
    path: '/test/async/task',
    name: 'test_async_beyond_request.task',
    requirements: ['_access' => 'TRUE'],
  )]
  public function task() : Response {
    EventLoop::defer(fn () => $this->state->set("async", TRUE));

    return new Response(NULL, Response::HTTP_NO_CONTENT);
  }

}
