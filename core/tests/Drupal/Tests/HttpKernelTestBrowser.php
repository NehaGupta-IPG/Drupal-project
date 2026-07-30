<?php

declare(strict_types=1);

namespace Drupal\Tests;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Resolves the kernel on each request to survive container rebuilds.
 *
 * @see \Drupal\Tests\HttpKernelUiHelperTrait::getDefaultDriverInstance()
 */
class HttpKernelTestBrowser extends HttpKernelBrowser {

  /**
   * Constructs an HttpKernelTestBrowser.
   */
  public function __construct() {
    parent::__construct(\Drupal::service(HttpKernelInterface::class));
  }

  /**
   * {@inheritdoc}
   */
  protected function doRequest(object $request): Response {
    // Re-resolve in case the container was rebuilt since construction.
    $this->kernel = \Drupal::service(HttpKernelInterface::class);
    return parent::doRequest($request);
  }

}
