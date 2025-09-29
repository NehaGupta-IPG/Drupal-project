<?php

namespace Drupal\Core\StackMiddleware;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Clean up the response headers when boosting a request.
 */
class HtmxHeaders implements HttpKernelInterface {

  /**
   * Constructs a new AjaxPageState instance.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $httpKernel
   *   The wrapped HTTP kernel.
   */
  public function __construct(protected readonly HttpKernelInterface $httpKernel) {
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    $response = $this->httpKernel->handle($request, $type, $catch);

    // When a link is boosted, automatically remove the technical informations
    // from the link saved in the browser history.
    if ($type === static::MAIN_REQUEST && $request->headers->get('HX-Boosted') === 'true') {
      $url = Url::createFromRequest($request);
      $url->setOption('query', UrlHelper::filterQueryParameters($request->query->all(), [
        MainContentViewSubscriber::WRAPPER_FORMAT,
        'ajax_page_state',
      ]));
      $response->headers->set('HX-Push-Url', $url->toString());
    }
    return $response;
  }

}
