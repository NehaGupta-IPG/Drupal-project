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
 * A test browser that isolates each request it makes against the kernel.
 *
 * Kernel tests reuse a single kernel and request stack across many requests
 * within the same PHP process, unlike a real HTTP request which starts from
 * a clean process. This browser makes each request behave like a real one
 * by isolating the request stack and resetting static state left over from
 * previous requests.
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
    $backup = $this->isolateRequestStack();
    try {
      return parent::doRequest($request);
    }
    finally {
      $this->restoreRequestStack($backup);
      $this->resetStaticState();
    }
  }

  /**
   * Temporarily removes all requests from the request stack.
   *
   * Kernel tests push a request for the '/' path onto the request stack when
   * the kernel is booted. Without isolation, that request remains the main
   * request for the lifetime of the test, so anything derived from the main
   * request would use the wrong request. For example, form actions would be
   * built from the boot request URI instead of the URI of the request being
   * handled. Popping the stack here ensures the request being handled next
   * becomes the main request, as it would be for a real HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\Request[]
   *   The requests removed from the stack, in the order they were popped, so
   *   that ::restoreRequestStack() can push them back afterwards.
   *
   * @see \Drupal\Tests\HttpKernelUiHelperTrait::getDefaultDriverInstance()
   * @see ::restoreRequestStack()
   */
  protected function isolateRequestStack(): array {
    $backup = [];
    while ($this->requestStack->getCurrentRequest() !== NULL) {
      $backup[] = $this->requestStack->pop();
    }
    return $backup;
  }

  /**
   * Restores requests previously removed from the request stack.
   *
   * @param \Symfony\Component\HttpFoundation\Request[] $backup
   *   The requests popped by ::isolateRequestStack().
   */
  protected function restoreRequestStack(array $backup): void {
    while ($popped = array_pop($backup)) {
      $this->requestStack->push($popped);
    }
  }

  /**
   * Resets static properties left over from previous requests.
   */
  protected function resetStaticState(): void {
    Html::resetSeenIds();
    (new FormState())->clearErrors();
  }

}
