<?php

declare(strict_types=1);

namespace Drupal\csrf_race_test\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller to test concurrent CSRF token generation.
 */
class TestController extends ControllerBase {

  /**
   * Token generator service.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $tokenGenerator;

  /**
   * Controller constructor.
   */
  public function __construct(CsrfTokenGenerator $token_generator) {
    $this->tokenGenerator = $token_generator;
  }

  /**
   * Helper page to load jQuery in test.
   *
   * @return array
   *   Empty page with jQuery.
   */
  #[Route(
    path: '/csrf_race/test',
    name: 'csrf_race_test.test',
    requirements: ['_access' => 'TRUE'],
  )]
  public function testMethod() {
    return [
      '#markup' => '',
      '#attached' => [
        'library' => 'core/jquery',
      ],
    ];
  }

  /**
   * Just return generated CSRF token for concurrent requests.
   *
   * We delay the response to the first request to make sure the second request
   * is made when the first is not yet finished.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   CSRF token.
   */
  #[Route(
    path: '/csrf_race/get_csrf_token/{num}',
    name: 'csrf_race_test.csrftoken',
    requirements: ['_access' => 'TRUE'],
  )]
  public function getCsrfToken(int $num) {
    sleep($num);
    return new JsonResponse($this->tokenGenerator->get());
  }

}
