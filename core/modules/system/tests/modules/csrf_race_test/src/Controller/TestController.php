<?php

declare(strict_types=1);

namespace Drupal\csrf_race_test\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('csrf_token')
    );
  }

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
  public function testMethod(): array {
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
  public function getCsrfToken(int $num): \Symfony\Component\HttpFoundation\JsonResponse {
    sleep($num);
    return new JsonResponse($this->tokenGenerator->get());
  }

}
