<?php

namespace Drupal\media\OEmbed;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// cspell:ignore nocdata

/**
 * Fetches and stores oEmbed resources.
 *
 * Resource data is stored in an expirable key/value collection, so it survives
 * cache clears. Before stored data expires, a queue item is added to refresh
 * it, so the data can be served without a blocking HTTP request.
 */
class ResourceFetcher implements ResourceFetcherInterface {

  /**
   * The name of the queue used to refresh stored resources.
   */
  const QUEUE_NAME = 'media_oembed_resource_refresh';

  /**
   * The key/value collection used to store resource data.
   */
  const KEY_VALUE_COLLECTION = 'media.oembed_resource';

  /**
   * The number of seconds stored resource data is kept for.
   */
  const MAX_AGE = 2592000;

  /**
   * The number of seconds after which stored resource data is refreshed.
   */
  const REFRESH_AGE = 1814400;

  /**
   * The number of seconds after which a refresh is queued again.
   *
   * This allows a refresh to be retried when a queue item was lost.
   */
  const REQUEUE_AGE = 86400;

  public function __construct(
    protected ClientInterface $httpClient,
    protected ProviderRepositoryInterface $providers,
    #[Autowire(service: 'cache.default')]
    protected CacheBackendInterface $cacheBackend,
    protected int $timeout = 5,
    protected ?KeyValueExpirableFactoryInterface $keyValueExpirableFactory = NULL,
    protected ?QueueFactory $queueFactory = NULL,
    protected ?TimeInterface $time = NULL,
  ) {
    if (!$this->keyValueExpirableFactory) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $keyValueExpirableFactory argument is deprecated in drupal:12.1.0 and will be required in drupal:13.0.0. See https://www.drupal.org/node/3634964', E_USER_DEPRECATED);
      $this->keyValueExpirableFactory = \Drupal::service('keyvalue.expirable');
    }
    if (!$this->queueFactory) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $queueFactory argument is deprecated in drupal:12.1.0 and will be required in drupal:13.0.0. See https://www.drupal.org/node/3634964', E_USER_DEPRECATED);
      $this->queueFactory = \Drupal::service('queue');
    }
    if (!$this->time) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $time argument is deprecated in drupal:12.1.0 and will be required in drupal:13.0.0. See https://www.drupal.org/node/3634964', E_USER_DEPRECATED);
      $this->time = \Drupal::time();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fetchResource($url) {
    $key_value = $this->keyValueExpirableFactory->get(static::KEY_VALUE_COLLECTION);
    $key = Crypt::hashBase64($url);

    $stored = $key_value->get($key);
    if ($stored) {
      $now = $this->time->getCurrentTime();
      // Queue a refresh when the data is due for one, unless a refresh was
      // queued recently.
      if ($stored['refresh'] <= $now && (!$stored['queued'] || $stored['queued'] + static::REQUEUE_AGE <= $now)) {
        $this->queueFactory->get(static::QUEUE_NAME)->createItem(['url' => $url]);
        $stored['queued'] = $now;
        $key_value->setWithExpire($key, $stored, max(1, $stored['expires'] - $now));
      }
      return $this->createResource($stored['data'], $url);
    }

    $data = $this->refreshResource($url);
    return $this->createResource($data, $url);
  }

  /**
   * Fetches oEmbed resource data from the provider and stores it.
   *
   * @param string $url
   *   Endpoint-specific URL of the oEmbed resource.
   *
   * @return array
   *   The resource data returned by the provider.
   *
   * @throws \Drupal\media\OEmbed\ResourceException
   *   If the oEmbed endpoint is not reachable or the response cannot be
   *   decoded.
   */
  public function refreshResource(string $url): array {
    $data = $this->doFetchResource($url);

    $now = $this->time->getCurrentTime();
    $this->keyValueExpirableFactory->get(static::KEY_VALUE_COLLECTION)->setWithExpire(Crypt::hashBase64($url), [
      'data' => $data,
      'refresh' => $now + static::REFRESH_AGE,
      'expires' => $now + static::MAX_AGE,
      'queued' => NULL,
    ], static::MAX_AGE);

    return $data;
  }

  /**
   * Fetches oEmbed resource data from the provider.
   *
   * @param string $url
   *   Endpoint-specific URL of the oEmbed resource.
   *
   * @return array
   *   The resource data returned by the provider.
   *
   * @throws \Drupal\media\OEmbed\ResourceException
   *   If the oEmbed endpoint is not reachable or the response cannot be
   *   decoded.
   */
  protected function doFetchResource(string $url): array {
    try {
      $response = $this->httpClient->request('GET', $url, [
        RequestOptions::TIMEOUT => $this->timeout,
      ]);
    }
    catch (ClientExceptionInterface $e) {
      throw new ResourceException('Could not retrieve the oEmbed resource.', $url, [], $e);
    }

    [$format] = $response->getHeader('Content-Type');
    $content = (string) $response->getBody();

    if (strstr($format, 'text/xml') || strstr($format, 'application/xml')) {
      $data = $this->parseResourceXml($content, $url);
    }
    // By default, try to parse the resource data as JSON.
    else {
      $data = Json::decode($content);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new ResourceException('Error decoding oEmbed resource: ' . json_last_error_msg(), $url);
      }
    }
    if (empty($data) || !is_array($data)) {
      throw new ResourceException('The oEmbed resource could not be decoded.', $url);
    }

    return $data;
  }

  /**
   * Creates a Resource object from raw resource data.
   *
   * @param array $data
   *   The resource data returned by the provider.
   * @param string $url
   *   The URL of the resource.
   *
   * @return \Drupal\media\OEmbed\Resource
   *   A value object representing the resource.
   *
   * @throws \Drupal\media\OEmbed\ResourceException
   *   If the resource cannot be created.
   */
  protected function createResource(array $data, $url) {
    $data += [
      'title' => NULL,
      'author_name' => NULL,
      'author_url' => NULL,
      'provider_name' => NULL,
      'cache_age' => NULL,
      'thumbnail_url' => NULL,
      'thumbnail_width' => NULL,
      'thumbnail_height' => NULL,
      'width' => NULL,
      'height' => NULL,
      'url' => NULL,
      'html' => NULL,
      'version' => NULL,
    ];

    if ($data['version'] !== '1.0') {
      throw new ResourceException("Resource version must be '1.0'", $url, $data);
    }

    // Prepare the arguments to pass to the factory method.
    $provider = $data['provider_name'] ? $this->providers->get($data['provider_name']) : NULL;

    // The Resource object will validate the data we create it with and throw an
    // exception if anything looks wrong. For better debugging, catch those
    // exceptions and wrap them in a more specific and useful exception.
    try {
      switch ($data['type']) {
        case Resource::TYPE_LINK:
          return Resource::link(
            $data['url'],
            $provider,
            $data['title'],
            $data['author_name'],
            $data['author_url'],
            $data['cache_age'],
            $data['thumbnail_url'],
            $data['thumbnail_width'],
            $data['thumbnail_height']
          );

        case Resource::TYPE_PHOTO:
          return Resource::photo(
            $data['url'],
            $data['width'],
            $data['height'],
            $provider,
            $data['title'],
            $data['author_name'],
            $data['author_url'],
            $data['cache_age'],
            $data['thumbnail_url'],
            $data['thumbnail_width'],
            $data['thumbnail_height']
          );

        case Resource::TYPE_RICH:
          return Resource::rich(
            $data['html'],
            $data['width'],
            $data['height'],
            $provider,
            $data['title'],
            $data['author_name'],
            $data['author_url'],
            $data['cache_age'],
            $data['thumbnail_url'],
            $data['thumbnail_width'],
            $data['thumbnail_height']
          );

        case Resource::TYPE_VIDEO:
          return Resource::video(
            $data['html'],
            $data['width'],
            $data['height'],
            $provider,
            $data['title'],
            $data['author_name'],
            $data['author_url'],
            $data['cache_age'],
            $data['thumbnail_url'],
            $data['thumbnail_width'],
            $data['thumbnail_height']
          );

        default:
          throw new ResourceException('Unknown resource type: ' . $data['type'], $url, $data);
      }
    }
    catch (\InvalidArgumentException $e) {
      throw new ResourceException($e->getMessage(), $url, $data, $e);
    }
  }

  /**
   * Parses XML resource data.
   *
   * @param string $data
   *   The raw XML for the resource.
   * @param string $url
   *   The resource URL.
   *
   * @return array
   *   The parsed resource data.
   *
   * @throws \Drupal\media\OEmbed\ResourceException
   *   If the resource data could not be parsed.
   */
  protected function parseResourceXml($data, $url) {
    // Enable userspace error handling.
    $was_using_internal_errors = libxml_use_internal_errors(TRUE);
    libxml_clear_errors();

    $content = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
    // Restore the previous error handling behavior.
    libxml_use_internal_errors($was_using_internal_errors);

    $error = libxml_get_last_error();
    if ($error) {
      libxml_clear_errors();
      throw new ResourceException($error->message, $url);
    }
    elseif ($content === FALSE) {
      throw new ResourceException('The fetched resource could not be parsed.', $url);
    }

    // Convert XML to JSON so that the parsed resource has a consistent array
    // structure, regardless of any XML attributes or quirks of the XML parser.
    $data = Json::encode($content);
    return Json::decode($data);
  }

}
