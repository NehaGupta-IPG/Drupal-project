<?php

namespace Drupal\Core\Runtime;

use Drupal\Core\DrupalKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * The Drupal Kernel Runner.
 *
 * This is used by the DrupalRuntime to process a single request with a
 * DrupalKernel in the context of a webserver use-case such as PHP-FPM with
 * nginx or Apache.
 */
class DrupalKernelRunner implements RunnerInterface {

  /**
   * Create a new DrupalKernelRunner instance.
   *
   * @param \Drupal\Core\DrupalKernelInterface $kernel
   *   The DrupalKernel that should be used to handle the request.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request that's being handled.
   */
  public function __construct(
    private DrupalKernelInterface $kernel,
    private Request $request,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function run(): int {
    $response = $this->kernel->handle($this->request);
    $response->send();

    if ($this->kernel instanceof TerminableInterface) {
      $this->kernel->terminate($this->request, $response);
    }

    return 0;
  }

}
