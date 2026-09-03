<?php

declare(strict_types=1);

namespace Drupal\path_changed_helper_test\Controller;

use Drupal\Core\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for testing the PathChangedHelper class.
 */
class TestController {

  #[Route(
    path: '/new/path/{block_type}',
    name: 'path.changed',
    requirements: ['_access' => 'TRUE'],
  )]
  #[Route(
    path: '/old/path/{block_type}',
    name: 'path.changed.bc',
    requirements: ['_access' => 'TRUE'],
  )]

  public function test(): Response {
    return new Response('test');
  }

}
