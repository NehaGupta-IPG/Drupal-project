<?php

declare(strict_types=1);

namespace Drupal\path_changed_helper_test\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for testing the PathChangedHelper class.
 */
class TestController {

  /**
   * Returns a simple response with test content.
   */
  public function test(): Response {
    return new Response('test');
  }

}
