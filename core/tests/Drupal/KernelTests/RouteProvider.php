<?php

declare(strict_types=1);

namespace Drupal\KernelTests;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Routing\PreloadableRouteProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Rebuilds the router when the provider is instantiated.
 */
class RouteProvider implements PreloadableRouteProviderInterface {

  use DependencySerializationTrait;

  /**
   * Whether the initial router rebuild has been triggered.
   */
  protected bool $rebuilt = FALSE;

  /**
   * Loads the real route provider from the container and rebuilds the router.
   *
   * @return \Drupal\Core\Routing\PreloadableRouteProviderInterface
   *   The route provider.
   */
  protected function lazyLoadItself(): PreloadableRouteProviderInterface {
    $container = \Drupal::getContainer();
    if (!$this->rebuilt) {
      $this->rebuilt = TRUE;
      $container->get('router.builder')->rebuild();
    }
    return $container->get('test.router.route_provider');
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteCollectionForRequest(Request $request): RouteCollection {
    return $this->lazyLoadItself()->getRouteCollectionForRequest($request);
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteByName($name): Route {
    return $this->lazyLoadItself()->getRouteByName($name);
  }

  /**
   * {@inheritdoc}
   */
  public function preLoadRoutes($names) {
    return $this->lazyLoadItself()->preLoadRoutes($names);
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutesByNames($names): Route|array {
    return $this->lazyLoadItself()->getRoutesByNames($names);
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutesByPattern($pattern): RouteCollection {
    return $this->lazyLoadItself()->getRoutesByPattern($pattern);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllRoutes(): \Traversable {
    return $this->lazyLoadItself()->getAllRoutes();
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    return $this->lazyLoadItself()->reset();
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteAliases(string $route_name): iterable {
    return $this->lazyLoadItself()->getRouteAliases($route_name);
  }

}
