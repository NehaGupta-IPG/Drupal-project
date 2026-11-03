<?php

namespace Drupal\Core\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext as SymfonyRequestContext;

/**
 * Holds information about the current request.
 */
class RequestContext extends SymfonyRequestContext {

  /**
   * The scheme, host and base path, for example "https://example.com/d8".
   *
   * @var string
   */
  protected $completeBaseUrl;

  /**
   * Populates the context from the current request from the request stack.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The current request stack.
   */
  public function fromRequestStack(RequestStack $request_stack) {
    $request = $request_stack->getCurrentRequest();
    // The request stack can be empty during request-less execution, such as
    // route rebuilds triggered from the CLI (for example a module's install
    // hook or event subscriber running under `drush site:install`). In that
    // case there is nothing to populate the context from, so leave it at its
    // constructor defaults instead of passing a NULL request to
    // static::fromRequest(), which requires a Request object.
    if ($request) {
      $this->fromRequest($request);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fromRequest(Request $request): static {
    // @todo Extract the code in DrupalKernel::initializeRequestGlobals.
    //   See https://www.drupal.org/node/2404601
    if (isset($GLOBALS['base_url'])) {
      $this->setCompleteBaseUrl($GLOBALS['base_url']);
    }

    return parent::fromRequest($request);
  }

  /**
   * Gets the scheme, host and base path.
   *
   * For example, in an installation in a subdirectory "d8", it should be
   * "https://example.com/d8".
   */
  public function getCompleteBaseUrl() {
    return $this->completeBaseUrl;
  }

  /**
   * Sets the complete base URL for the Request context.
   *
   * @param string $complete_base_url
   *   The complete base URL.
   */
  public function setCompleteBaseUrl($complete_base_url) {
    $this->completeBaseUrl = $complete_base_url;
  }

}
