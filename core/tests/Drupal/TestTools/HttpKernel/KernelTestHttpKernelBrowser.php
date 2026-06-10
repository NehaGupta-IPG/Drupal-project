<?php

declare(strict_types=1);

namespace Drupal\TestTools\HttpKernel;

use Symfony\Component\HttpKernel\HttpKernelBrowser;

/**
 * Browserkit client for use in kernel tests.
 *
 * This overrides getAbsoluteUri() to skip the conversion from a relative URI to
 * an absolute URI. We are using this with the HTTP kernel and that needs a
 * relative URI.
 */
class KernelTestHttpKernelBrowser extends HttpKernelBrowser {

  /**
   * {@inheritdoc}
   */
  protected function getAbsoluteUri(string $uri): string {
    // Preserve the given relative URI.
    return $uri;
  }

}
