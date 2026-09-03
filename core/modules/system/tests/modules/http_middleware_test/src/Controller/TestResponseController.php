<?php

declare(strict_types=1);

namespace Drupal\http_middleware_test\Controller;

use Drupal\Core\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller routines for http_middleware_test routes.
 */
final class TestResponseController {

  /**
   * Returns a test response.
   */
  #[Route(
    path: '/test-response',
    name: 'http_middleware_test.test_response',
    requirements: ['_access' => 'TRUE'],
  )]
  public function testResponse(): Response {
    return new Response('<html><body><p>Mangoes</p></body></html>');
  }

}
