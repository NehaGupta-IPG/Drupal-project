<?php

declare(strict_types=1);

namespace Drupal\legacy_front_controller_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller to test a legacy front controller.
 */
class TestController extends ControllerBase {

  #[Route(
    path: '/test',
    name: 'legacy_front_controller_test.home',
    requirements: ['_access' => 'TRUE'],
  )]

  public function helloWorld() : Response {
    return new Response("Hello World");
  }

}
