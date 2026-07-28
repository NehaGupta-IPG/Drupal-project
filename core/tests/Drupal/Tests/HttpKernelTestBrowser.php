<?php

declare(strict_types=1);

namespace Drupal\Tests;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormState;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * A browser which isolates the request stack for each request it handles.
 *
 * Kernel tests push a request for the '/' path onto the request stack when
 * the kernel is booted. Without isolation, that request remains the main
 * request for the lifetime of the test, so anything derived from the main
 * request would use the wrong request. For example, form actions would be
 * built from the boot request URI instead of the URI of the request being
 * handled.
 *
 * @see \Drupal\Tests\HttpKernelUiHelperTrait::getDefaultDriverInstance()
 */
class HttpKernelTestBrowser extends HttpKernelBrowser {

  public function __construct(
    HttpKernelInterface $kernel,
    protected RequestStack $requestStack,
  ) {
    parent::__construct($kernel);
  }

  /**
   * {@inheritdoc}
   */
  protected function doRequest(object $request): Response {
    Html::resetSeenIds();
    // Reset static properties from previous requests.
    (new FormState())->clearErrors();

    // Temporarily remove all requests from the request stack, so that the
    // request being handled is the main request, as it would be for a real
    // HTTP request.
    $backup = [];
    while ($this->requestStack->getCurrentRequest() !== NULL) {
      $backup[] = $this->requestStack->pop();
    }
    try {
      return parent::doRequest($request);
    }
    finally {
      while ($popped = array_pop($backup)) {
        $this->requestStack->push($popped);
      }
    }
  }

}
