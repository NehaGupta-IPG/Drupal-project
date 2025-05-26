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
   * Constructs a new KernelPreHandle instance.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $httpKernel
   *   The wrapped HTTP kernel.
   * @param \Drupal\Core\DrupalKernelInterface $drupalKernel
   *   The main Drupal kernel.
   */
  public function __construct(
    protected HttpKernelInterface $httpKernel,
    protected DrupalKernelInterface $drupalKernel,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    $this->drupalKernel->preHandle($request);

    return $this->httpKernel->handle($request, $type, $catch);
  }

}
