<?php

declare(strict_types=1);

namespace Drupal\user_language_test\Controller;

use Drupal\Core\Routing\Attribute\Route;

/**
 * Returns responses for User Language Test routes.
 */
class UserLanguageTestController {

  /**
   * Builds the response.
   */
  #[Route(
    path: '/user-language-test/post',
    name: 'user_language_test.post_response',
    requirements: ['_access' => 'TRUE'],
    options: ['_admin_route' => TRUE],
    methods: ['post'],
  )]
  public function buildPostResponse() {
    return ['#markup' => 'It works!'];
  }

}
