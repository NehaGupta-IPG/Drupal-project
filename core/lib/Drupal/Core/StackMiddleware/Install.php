<?php

namespace Drupal\Core\StackMiddleware;

use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\State\StateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Redirects to the installer if install tasks are not done.
 */
class Install implements HttpKernelInterface {

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The Drupal state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new Install instance.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The wrapped HTTP kernel.
   * @param \Drupal\Core\State\StateInterface $state
   *   The Drupal state service.
   */
  public function __construct(HttpKernelInterface $http_kernel, StateInterface $state) {
    $this->httpKernel = $http_kernel;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    $task = $this->state->get('install_task', 'done');
    if ($task !== 'done') {
      // Only redirect if this is an HTML response (i.e., a user trying to view
      // the site in a web browser before installing it).
      $format = $request->query->get(MainContentViewSubscriber::WRAPPER_FORMAT, $request->getRequestFormat());
      if ($format == 'html') {
        // Make sure the next request uses the state set by the installer.
        $this->state->resetCache();
        return new RedirectResponse($request->getBasePath() . '/core/install.php', 302, ['Cache-Control' => 'no-cache']);
      }
    }
    return $this->httpKernel->handle($request, $type, $catch);
  }

}
