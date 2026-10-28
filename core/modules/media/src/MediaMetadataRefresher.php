<?php

declare(strict_types=1);

namespace Drupal\media;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Drupal\media\OEmbed\UrlResolverInterface;
use Drupal\media\Plugin\media\Source\OEmbedInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Refreshes media metadata and thumbnails from their source.
 *
 * @internal
 */
final class MediaMetadataRefresher {

  /**
   * Constructs a MediaMetadataRefresher.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Closure $urlResolverClosure
   *   A closure returning the oEmbed URL resolver. It is lazy because this
   *   service is built when the media actions are installed, before the oEmbed
   *   provider settings exist.
   * @param \Closure $resourceFetcherClosure
   *   A closure returning the oEmbed resource fetcher, lazy for the same
   *   reason.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend the oEmbed resource fetcher uses.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[AutowireServiceClosure(UrlResolverInterface::class)]
    protected readonly \Closure $urlResolverClosure,
    #[AutowireServiceClosure(ResourceFetcherInterface::class)]
    protected readonly \Closure $resourceFetcherClosure,
    #[Autowire(service: 'cache.default')]
    protected readonly CacheBackendInterface $cacheBackend,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Checks whether a media item's source can be read.
   *
   * For oEmbed media this fetches the resource again, so a refresh that
   * follows uses the provider's current data rather than a cached copy.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media item.
   *
   * @return \Drupal\media\MediaSourceStatus
   *   The status of the media item's source.
   */
  public function check(MediaInterface $media): MediaSourceStatus {
    $source = $media->getSource();
    $value = $source->getSourceFieldValue($media);
    if ($value === NULL || $value === '') {
      return MediaSourceStatus::Missing;
    }

    $field_name = $source->getSourceFieldDefinition($media->bundle->entity)->getName();
    $item = $media->get($field_name)->first();
    if ($item instanceof FileItem) {
      $file = $item->entity;
      if (!$file instanceof FileInterface || !file_exists($file->getFileUri())) {
        return MediaSourceStatus::Missing;
      }
    }

    if ($source instanceof OEmbedInterface) {
      try {
        $resource_url = ($this->urlResolverClosure)()->getResourceUrl($value);
      }
      catch (ResourceException) {
        return MediaSourceStatus::Unavailable;
      }
      // The resource fetcher caches resources without expiry, which would
      // make a refresh reuse the data fetched when the item was created.
      // @see \Drupal\media\OEmbed\ResourceFetcher::fetchResource()
      $this->cacheBackend->delete("media:oembed_resource:$resource_url");
      // Fetch the resource here rather than through the source plugin, which
      // would show its own error message on top of the confirmation form.
      try {
        ($this->resourceFetcherClosure)()->fetchResource($resource_url);
      }
      catch (ResourceException) {
        return MediaSourceStatus::Unavailable;
      }
    }

    return MediaSourceStatus::Available;
  }

  /**
   * Checks whether a media item's thumbnail is one of the media icons.
   *
   * This is the case once a thumbnail has been removed, so there is nothing
   * left to remove.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media item.
   *
   * @return bool
   *   TRUE if the thumbnail is an icon, FALSE otherwise.
   */
  public function hasIconThumbnail(MediaInterface $media): bool {
    $uri = $media->get('thumbnail')->entity?->getFileUri();
    $icon_base_uri = $this->configFactory->get('media.settings')->get('icon_base_uri');
    return $uri !== NULL && str_starts_with($uri, $icon_base_uri . '/');
  }

  /**
   * Refreshes a media item's metadata and thumbnail from its source and saves.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media item.
   */
  public function refresh(MediaInterface $media): void {
    $media->enforceMetadataUpdate();
    $this->entityTypeManager->getStorage('media')->save($media);
  }

}
