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
   * The route provider service.
   */
  protected PreloadableRouteProviderInterface $service;

  /**
   * Loads the real route provider from the container and rebuilds the router.
   *
   * @return \Drupal\Core\Routing\PreloadableRouteProviderInterface
   *   The route provider.
   */
  protected function lazyLoadItself(): PreloadableRouteProviderInterface {
    if (!isset($this->service)) {
      $container = \Drupal::getContainer();
      $this->service = $container->get('test.router.route_provider');
      $container->get('router.builder')->rebuild();
    }

    return $this->service;
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
