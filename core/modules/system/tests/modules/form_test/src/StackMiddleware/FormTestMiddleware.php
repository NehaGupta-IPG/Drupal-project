<?php

declare(strict_types=1);

namespace Drupal\form_test\StackMiddleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Provides a test middleware which sets a custom response header.
 */
class FormTestMiddleware implements HttpKernelInterface {

  /**
   * The decorated kernel.
   *
   * @var HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Constructs a FormTestMiddleware object.
   *
   * @param HttpKernelInterface $http_kernel
   *   The decorated kernel.
   */
  public function __construct(HttpKernelInterface $http_kernel) {
    $this->httpKernel = $http_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    $response = $this->httpKernel->handle($request, $type, $catch);
    $response->headers->set('X-Form-Test-Stack-Middleware', 'invoked');
    return $response;
  }

}
