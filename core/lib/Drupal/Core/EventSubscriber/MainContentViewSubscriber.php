<?php

namespace Drupal\Core\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * View subscriber rendering main content render arrays into responses.
 *
 * Additional target rendering formats can be defined by adding another service
 * that implements \Drupal\Core\Render\MainContent\MainContentRendererInterface
 * and tagging it as a @code render.main_content_renderer @endcode, then
 * \Drupal\Core\Render\MainContent\MainContentRenderersPass will detect it and
 * use it when appropriate.
 *
 * @see \Drupal\Core\Render\MainContent\MainContentRendererInterface
 * @see \Drupal\Core\Render\MainContentControllerPass
 */
class MainContentViewSubscriber implements EventSubscriberInterface {

  /**
   * The class resolver service.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The available main content renderer services, keyed per format.
   *
   * @var array
   */
  protected $mainContentRenderers;

  /**
   * URL query attribute to indicate the wrapper used to render a request.
   *
   * The wrapper format determines how the HTML is wrapped, for example in a
   * modal dialog.
   */
  const WRAPPER_FORMAT = '_wrapper_format';

  /**
   * Constructs a new MainContentViewSubscriber object.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param array $main_content_renderers
   *   The available main content renderer service IDs, keyed by format.
   */
  public function __construct(ClassResolverInterface $class_resolver, RouteMatchInterface $route_match, array $main_content_renderers) {
    $this->classResolver = $class_resolver;
    $this->routeMatch = $route_match;
    $this->mainContentRenderers = $main_content_renderers;
  }

  /**
   * Sets a response given a (main content) render array.
   *
   * @param \Symfony\Component\HttpKernel\Event\ViewEvent $event
   *   The event to process.
   */
  public function onViewRenderArray(ViewEvent $event) {
    $request = $event->getRequest();
    $result = $event->getControllerResult();

    // Render the controller result into a response if it's a render array.
    if (is_array($result) && ($request->query->has(static::WRAPPER_FORMAT) || $request->getRequestFormat() == 'html')) {
      $wrapper = $request->query->get(static::WRAPPER_FORMAT, 'html');

      // Fall back to HTML if the requested wrapper envelope is not available.
      $wrapper = isset($this->mainContentRenderers[$wrapper]) ? $wrapper : 'html';

      $renderer = $this->classResolver->getInstanceFromDefinition($this->mainContentRenderers[$wrapper]);
      $response = $renderer->renderResponse($result, $request, $this->routeMatch);
      // The main content render array is rendered into a different Response
      // object, depending on the specified wrapper format.
      if ($response instanceof CacheableResponseInterface) {
        $main_content_view_subscriber_cacheability = (new CacheableMetadata())->setCacheContexts(['url.query_args:' . static::WRAPPER_FORMAT]);
        $response->addCacheableDependency($main_content_view_subscriber_cacheability);
      }
      $event->setResponse($response);
    }
  }

  /**
   * Ensures that requests with wrapper formats also have functioning redirects.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The Event to process.
   */
  public function onRedirectResponse(ResponseEvent $event): void {
    $response = $event->getResponse();
    $url = '';
    // Only local redirects need to be updated to have the wrapper format.
    if ($response instanceof LocalRedirectResponse) {
      $request = $event->getRequest();
      if ($request->getRequestFormat() === 'html' && $request->query->has(static::WRAPPER_FORMAT)) {
        $old_redirect_url = $response->getTargetUrl();
        $wrapper_format_query = [static::WRAPPER_FORMAT => $request->query->get(static::WRAPPER_FORMAT)];
        $parts = parse_url($old_redirect_url, PHP_URL_QUERY | PHP_URL_FRAGMENT);
        // Append the query to the URL.
        if ($parts === NULL || (!isset($parts['query']) && !isset($parts['fragment']))) {
          $url = $old_redirect_url . '?' . UrlHelper::buildQuery($wrapper_format_query);
        }
        // Update the existing query in the URL.
        elseif (isset($parts['query'])) {
          $query = [];
          parse_str($parts['query'], $query);
          $query = $wrapper_format_query;
          $url = str_replace($parts['query'], UrlHelper::buildQuery($query), $old_redirect_url);
        }
        // Handle when there is a URL fragment and no query.
        elseif (isset($parts['fragment'])) {
          $url = str_replace('#' . $parts['fragment'], '', $old_redirect_url);
          $url .= '?' . UrlHelper::buildQuery($wrapper_format_query);
          $url .= '#' . $parts['fragment'];
        }
        if ($url) {
          $response->setTargetUrl($url);
        }
        $main_content_view_subscriber_cacheability = (new CacheableMetadata())->setCacheContexts(['url' . static::WRAPPER_FORMAT]);
        $response->addCacheableDependency($main_content_view_subscriber_cacheability);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::VIEW][] = ['onViewRenderArray'];
    $events[KernelEvents::RESPONSE][] = ['onRedirectResponse'];

    return $events;
  }

}
