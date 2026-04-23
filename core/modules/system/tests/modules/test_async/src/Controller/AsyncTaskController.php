<?php

namespace Drupal\test_async\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\State\StateInterface;
use Revolt\EventLoop;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AsyncTaskController extends ControllerBase {

  public function __construct(
    protected StateInterface $state,
  ) {}

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
