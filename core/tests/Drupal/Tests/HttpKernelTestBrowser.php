<?php

declare(strict_types=1);

namespace Drupal\Tests;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Extends HttpKernelBrowser to reset resettable services after each request.
 *
 * In kernel tests, HttpKernelBrowser calls StackedHttpKernel::terminate()
 * rather than DrupalKernel::terminate(). This means services tagged with
 * kernel.reset are not reset between requests, allowing per-request state
 * to leak across sub-requests. This subclass calls the services_resetter
 * after each request to match the behavior of DrupalKernel::terminate().
 */
class HttpKernelTestBrowser extends HttpKernelBrowser {

  public function __construct(
    HttpKernelInterface $kernel,
    private readonly ContainerInterface $container,
  ) {
    parent::__construct($kernel);
  }

  /**
   * {@inheritdoc}
   */
  protected function doRequest(object $request): Response {
    $response = parent::doRequest($request);
    if ($this->container->has('services_resetter')) {
      $this->container->get('services_resetter')->reset();
    }
    return $response;
  }

}
