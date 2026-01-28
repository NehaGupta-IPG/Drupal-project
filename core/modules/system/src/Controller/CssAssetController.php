<?php

namespace Drupal\system\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
  public function deliver(Request $request, string $file_name) {
    if ($request->query->has('libraries') && !$request->query->has('categories')) {
      throw new BadRequestHttpException('Category must be passed when libraries are passed.');
    }
    if ($request->query->has('categories')) {
      $query_categories = json_decode(UrlHelper::uncompressQueryParameter($request->query->get('categories')), TRUE);
      if (!$query_categories) {
        throw new BadRequestHttpException('Categories must be valid CSS categories.');
      }
      foreach ($query_categories as $categories) {
        foreach ($categories as $category) {
          if (!defined('CSS_' . strtoupper($category))) {
            throw new BadRequestHttpException('Categories must be valid CSS categories.');
          }
        }
      }
      if (!$request->query->has('libraries')) {
        throw new BadRequestHttpException('Libraries must be passed when categories are passed.');
      }
    }
    return parent::deliver($request, $file_name);
  }

  /**
   * {@inheritdoc}
   */
  protected function getGroups(AttachedAssetsInterface $attached_assets, Request $request): array {
    $language = $this->languageManager()->getLanguage($request->get('language'));
    if ($request->query->has('libraries')) {
      $assets = $this->assetResolver->getCssAssets($attached_assets, FALSE, $language, FALSE);
      $groups = $this->grouper->group($assets);
      // Filter out files that don't belong to this group.
      $categories = json_decode(UrlHelper::uncompressQueryParameter($request->query->get('categories')), TRUE);
      foreach ($groups as $group_key => $group) {
        foreach ($group['items'] as $asset_key => $asset) {
          if (!isset($categories[$asset['library']]) || !in_array($asset['category'], $categories[$asset['library']], TRUE)) {
            unset($groups[$group_key]['items'][$asset_key]);
          }
        }
        if (empty($groups[$group_key]['items'])) {
          unset($groups[$group_key]);
        }
      }
      $groups = array_values($groups);
    }
    else {
      $assets = $this->assetResolver->getCssAssets($attached_assets, FALSE, $language);
      $groups = $this->grouper->group($assets);
    }

    return $groups;
  }

}
