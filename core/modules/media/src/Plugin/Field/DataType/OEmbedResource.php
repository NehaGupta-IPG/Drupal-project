<?php

namespace Drupal\media\Plugin\Field\DataType;

use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\TypedData\TypedData;
use Drupal\media\OEmbed\Resource;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Drupal\media\OEmbed\UrlResolverInterface;

class OEmbedResource extends TypedData {

  /**
   * Cached computed value.
   */
  protected Resource|null $value = NULL;

  protected static array $resourcedFetched = [];

  public function __construct(
    $definition,
    $name = NULL,
    ?TraversableTypedDataInterface $parent = NULL,
    protected ?UrlResolverInterface $urlResolver = NULL,
    protected ?ResourceFetcherInterface $resourceFetcher = NULL,
    protected ?LoggerChannel $logger = NULL,
  ) {
    parent::__construct($definition, $name, $parent);
  }

  public static function createInstance($definition, $name = NULL, ?TraversableTypedDataInterface $parent = NULL) {
    $container = \Drupal::getContainer();
    return new static(
      $definition,
      $name,
      $parent,
      $container->get(UrlResolverInterface::class),
      $container->get(ResourceFetcherInterface::class),
      $container->get('logger.channel.default'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {

    try {
      $value = $this->getParent()->getValue();
      // @todo do we need this cache?
      if (isset(static::$resourcedFetched[$value['value']][$this->name])) {
        return static::$resourcedFetched[$value['value']][$this->name];
      }
      $resource_url = $this->urlResolver->getResourceUrl($value['value'], 400, 400);
      if ($this->name === "url") {
        return $resource_url;
      }
      $resource = $this->resourceFetcher->fetchResource($resource_url);
      if ($this->name === "type") {
        return $resource->getType();
      }
      static::$resourcedFetched[$value['value']] = [
        'url' => $resource_url,
        'type' => $resource->getType(),
        'resource' => $resource,
      ];
      return $resource;
    }
    catch (ResourceException $exception) {
      $this->logger->error("Could not retrieve the remote URL (@url): %error", [
        '@url' => $value,
        '%error' => $exception->getPrevious() ? $exception->getPrevious()->getMessage() : $exception->getMessage(),
        'exception' => $exception,
      ]);
    }
    catch (\Exception $exception) {
      $this->logger->error("Something went wrong: %error", [
        '%error' => $exception->getPrevious() ? $exception->getPrevious()->getMessage() : $exception->getMessage(),
        'exception' => $exception,
      ]);
    }
    return NULL;
  }

}
