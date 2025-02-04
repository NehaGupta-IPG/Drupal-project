<?php

namespace Drupal\system\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Defines a routes' callback to register a URL for serving assets.
 */
class AssetRoutes implements ContainerInjectionInterface {

  public const string DEFAULT_DIRECTORY_PATH = '_drupal_assets';

  /**
   * Constructs an asset routes object.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager service.
   */
  public function __construct(
    protected readonly StreamWrapperManagerInterface $streamWrapperManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('stream_wrapper_manager')
    );
  }

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   */
  public function routes(): array {
    $routes = [];
    // Generate assets. If clean URLs are disabled image derivatives will always
    // be served through the routing system. If clean URLs are enabled and the
    // image derivative already exists, PHP will be bypassed.

    // It is possible to swap out the underlying stream wrapper implementation
    // for one that may not be "local." In this case, the stream wrapper does
    // not carry the same directory path metadata we can use to construct a
    // public URL, because the stream wrapper cannot be guaranteed to map to a
    // publicly-accessible directory on the web server. We use a sensible
    // default here which is namespaced to avoid conflicts. Note, this means
    // Drupal will always be inline of the request, even after the asset is
    // generated. Users implementing an alternative stream wrapper for assets
    // should consider placing this path behind a CDN, using a caching reverse
    // proxy or similar. Sites implementing this model must also consider the
    // cacheability of the piped binary response from AssetControllerBase, which
    // sets the Cache-control header to "private, no-store".
    $stream_wrapper = $this->streamWrapperManager->getViaScheme('assets');
    $directory_path = $stream_wrapper instanceof LocalStream
      ? $stream_wrapper->getDirectoryPath()
      : self::DEFAULT_DIRECTORY_PATH;

    $routes['system.css_asset'] = new Route(
      '/' . $directory_path . '/css/{file_name}',
      [
        '_controller' => 'Drupal\system\Controller\CssAssetController::deliver',
      ],
      [
        '_access' => 'TRUE',
      ]
    );
    $routes['system.js_asset'] = new Route(
      '/' . $directory_path . '/js/{file_name}',
      [
        '_controller' => 'Drupal\system\Controller\JsAssetController::deliver',
      ],
      [
        '_access' => 'TRUE',
      ]
    );
    return $routes;
  }

}
