<?php

declare(strict_types=1);

namespace Drupal\media_oembed_discovery\OEmbed;

use Drupal\Component\Utility\Html;
use Drupal\media\OEmbed\Provider;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\OEmbed\UrlResolver;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * URL resolver that falls back to oEmbed discovery for unknown URLs.
 *
 * When the media URL does not match any registered provider's endpoint
 * patterns, this resolver fetches the URL and inspects the returned HTML for
 * <link rel="alternate" type="application/json+oembed"> pointing at an oEmbed
 * resource.
 */
class DiscoveringUrlResolver extends UrlResolver {

  /**
   * {@inheritdoc}
   */
  public function getProviderByUrl($url): Provider {
    try {
      return parent::getProviderByUrl($url);
    }
    catch (ResourceException $e) {
      $resource_url = $this->discoverResourceUrl($url);
      if ($resource_url) {
        return $this->resourceFetcher->fetchResource($resource_url)->getProvider();
      }
      throw $e;
    }
  }

  /**
   * Runs oEmbed discovery and returns the endpoint URL if successful.
   *
   * @param string $url
   *   The resource's URL.
   *
   * @return string|false
   *   URL of the oEmbed endpoint, or FALSE if the discovery was unsuccessful.
   */
  protected function discoverResourceUrl(string $url): string|false {
    try {
      $response = $this->httpClient->get($url);
    }
    catch (ClientExceptionInterface) {
      return FALSE;
    }

    // Only care about HTML responses.
    $response_type = strtolower($response->getHeaderLine('Content-Type'));
    if (!str_contains($response_type, 'text/html')) {
      return FALSE;
    }

    $document = Html::load((string) $response->getBody());
    $xpath = new \DOMXPath($document);

    return $this->findUrl($xpath, 'json') ?: $this->findUrl($xpath, 'xml');
  }

  /**
   * Tries to find the oEmbed URL in a DOM.
   *
   * @param \DOMXPath $xpath
   *   Page HTML as DOMXPath.
   * @param string $format
   *   Format of oEmbed resource. Possible values are 'json' and 'xml'.
   *
   * @return string|false
   *   A URL to an oEmbed resource or FALSE if not found.
   */
  protected function findUrl(\DOMXPath $xpath, string $format): string|false {
    $result = $xpath->query("//link[@type='application/$format+oembed']");
    return $result->length ? $result->item(0)->getAttribute('href') : FALSE;
  }

}
