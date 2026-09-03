<?php

declare(strict_types=1);

namespace Drupal\path_encoded_test\Controller;

use Drupal\Core\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for path_encoded_test routes.
 */
class PathEncodedTestController {

  /**
   * Returns an HTML simple response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A simple HTML response.
   */
  #[Route(
    path: '/hi/llama:party',
    name: 'path_encoded_test.colon',
    requirements: ['_access' => 'TRUE'],
  )]
  #[Route(
    path: '/blog/@Dries',
    name: 'path_encoded_test.at_sign',
    requirements: ['_access' => 'TRUE'],
  )]
  #[Route(
    path: '/cat(box)',
    name: 'path_encoded_test.parentheses',
    requirements: ['_access' => 'TRUE'],
  )]
  public function simple() {
    return new Response('<html><body>PathEncodedTestController works</body></html>');
  }

}
