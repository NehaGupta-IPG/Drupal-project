<?php

namespace Drupal\system\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Asset\AssetGroupSetHashTrait;

/**
 * Defines a controller to serve CSS aggregates.
 */
class CssAssetController extends AssetControllerBase {

  use AssetGroupSetHashTrait;

  /**
   * {@inheritdoc}
   */
  protected string $contentType = 'text/css';

  /**
   * {@inheritdoc}
   */
  protected string $assetType = 'css';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('stream_wrapper_manager'),
      $container->get('library.dependency_resolver'),
      $container->get('asset.resolver'),
      $container->get('theme.initialization'),
      $container->get('theme.manager'),
      $container->get('asset.css.collection_grouper'),
      $container->get('asset.css.collection_optimizer'),
      $container->get('asset.css.dumper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getGroups(AttachedAssetsInterface $attached_assets, Request $request): array {
    $language = $this->languageManager()->getLanguage($request->get('language'));
    if ($request->query->has('libraries')) {
      // If we're creating an aggregate for a single library, we don't need the
      // dependencies because those will be handled by different aggregates, so
      // get a list of dependencies, then set them as already loaded.
      $with_dependencies = $this->libraryDependencyResolver->getLibrariesWithDependencies($attached_assets->getLibraries());
      $already_loaded = array_diff($with_dependencies, explode(',', UrlHelper::uncompressQueryParameter($request->query->get('libraries'))));
      $attached_assets->setAlreadyLoadedLibraries($already_loaded);
    }
    $assets = $this->assetResolver->getCssAssets($attached_assets, FALSE, $language);
    return $this->grouper->group($assets);
  }

}
