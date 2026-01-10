<?php

namespace Drupal\Core\Htmx;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;

/**
 * Optional data object for HX-Location.
 */
class HtmxLocationResponseData implements \Stringable {

  /**
   * Collects cache data for the URL.
   */
  protected CacheableMetadata $cacheableMetadata;

  public function __construct(
    public readonly Url $path,
    public readonly string $source = '',
    public readonly string $event = '',
    public readonly string $handler = '',
    public readonly string $target = '',
    public readonly string $swap = '',
    public readonly array $values = [],
    public readonly array $headers = [],
    public readonly string $select = '',
  ) {
    $this->cacheableMetadata = new CacheableMetadata();
  }

  /**
   * Returns non-empty data, JSON encoded.
   *
   * @return string
   *   The encoded data.
   */
  public function __toString(): string {
    /** @var \Drupal\Core\GeneratedUrl $generatedUrl */
    $generatedUrl = $this->path->toString(TRUE);
    $this->cacheableMetadata->addCacheableDependency($generatedUrl);
    $path = $generatedUrl->getGeneratedUrl();
    $data = [
      'path' => $path,
      'source' => $this->source,
      'event' => $this->event,
      'headers' => $this->headers,
      'handler' => $this->handler,
      'target' => $this->target,
      'swap' => $this->swap,
      'select' => $this->select,
      'values' => $this->values,
    ];
    $data = array_filter($data, static fn ($item) => $item !== '' && $item !== []);
    return json_encode($data);
  }

  /**
   * Retrieves the cacheable metadata associated with the URL.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cacheable metadata instance.
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return $this->cacheableMetadata;
  }

}
