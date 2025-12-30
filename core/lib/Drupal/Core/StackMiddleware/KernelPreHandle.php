<?php

namespace Drupal\Core\StackMiddleware;

use Drupal\Core\DrupalKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Prepares the environment after page caching ran.
 */
class KernelPreHandle implements HttpKernelInterface {

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The main Drupal kernel.
   *
   * @var \Drupal\Core\DrupalKernelInterface
   */
  protected $drupalKernel;

  public function __construct(HttpKernelInterface $http_kernel, DrupalKernelInterface $drupal_kernel) {
    $this->httpKernel = $http_kernel;
    $this->drupalKernel = $drupal_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    $this->drupalKernel->preHandle($request);

    return $this->httpKernel->handle($request, $type, $catch);
  }

}
