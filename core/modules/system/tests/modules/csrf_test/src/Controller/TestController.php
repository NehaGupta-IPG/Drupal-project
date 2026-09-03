<?php

declare(strict_types=1);

namespace Drupal\csrf_test\Controller;

use Drupal\Core\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Just a test controller for test routes.
 */
class TestController {

  /**
   * Just a test method for the test routes.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  #[Route(
    path: 'csrf/protected',
    name: 'csrf_test.protected',
    requirements: ['_csrf_request_header_token' => 'TRUE'],
    methods: ['POST'],
  )]
  #[Route(
    path: 'csrf/protected/read-only',
    name: 'csrf_test.protected_read_only',
    requirements: ['_access' => 'TRUE', '_csrf_request_header_token' => 'TRUE'],
    methods: ['GET'],
  )]
  #[Route(
    path: 'csrf/protected/route',
    name: 'csrf_test.route_with_csrf_token',
    requirements: ['_permission' => 'administer site configuration', '_csrf_token' => 'TRUE'],
  )]
  public function testMethod() {
    return new Response('Sometimes it is hard to think of test content!');
  }

}
