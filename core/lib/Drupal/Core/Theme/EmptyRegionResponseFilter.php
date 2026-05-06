<?php

namespace Drupal\Core\Theme;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to filter HTML responses, to filter empty regions.
 *
 * Regions generally contain blocks, but when those blocks are placeholdered,
 * it is not possible to determine in Twig whether to render the region or not
 * depending on whether blocks return content, because they are rendered after
 * the region itself when placeholders are replaced.
 */
class EmptyRegionResponseFilter implements EventSubscriberInterface {

  /**
   * Sets the 'is-active' class on links.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event): void {
    $response = $event->getResponse();

    // Only care about HTML responses.
    if (stripos($response->headers->get('Content-Type', ''), 'text/html') === FALSE) {
      return;
    }

    // If content is FALSE, assume the response does not support the
    // setContent() method and skip it, for example,
    // \Symfony\Component\HttpFoundation\BinaryFileResponse.
    $content = $response->getContent();
    if ($content !== FALSE) {
      $response->setContent(static::filterEmptyRegions(
        $content,
      ));
    }
  }

  /**
   * Filters empty theme regions from HTML responses.
   */
  public static function filterEmptyRegions($html_markup): string {
    if (!str_contains($html_markup, 'data-filter-empty-region')) {
      return $html_markup;
    }
    $dom = new \DOMDocument();
    @$dom->loadHTML($html_markup);

    $dom_xpath = new \DOMXPath($dom);

    $all_attributes = $dom_xpath->query('//@data-filter-empty-region');
    if ($all_attributes) {
      foreach ($all_attributes as $attribute) {
        $region = $attribute->parentElement;
        // The region may include line breaks, so compare against a trimmed
        // version of the element content.
        if (trim($region->nodeValue) === '') {
          $region->remove();
        }
        else {
          // If we're not removing the region, remove the attribute.
          $region->removeAttribute('data-filter-empty-region');
        }
      }
      return $dom->saveHtml();
    }
    return $html_markup;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['onResponse', -511];

    return $events;
  }

}
