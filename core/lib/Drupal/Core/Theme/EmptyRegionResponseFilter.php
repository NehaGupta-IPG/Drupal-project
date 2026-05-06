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
    $dom = \Dom\HTMLDocument::createFromString($html_markup, LIBXML_NOERROR);

    $dom_xpath = new \Dom\XPath($dom);

    $all_attributes = $dom_xpath->query('//@data-filter-empty-region');

    // While we use the DOM to find the correct HTML to replace, string
    // replacement is done directly against the HTML string to minimize changes
    // to the document as a whole.
    if ($all_attributes) {
      foreach ($all_attributes as $attribute) {
        $region = $attribute->parentElement;
        $before_markup = $dom->saveHtml($region);
        // If the region has no child elements, it may still have a text
        // element, and that text element may consist of only whitespace. Ensure
        // that we don't replace regions with content but also that any
        // whitespace is trimmed.
        if ($region->childElementCount === 0 && trim($region->textContent) === '') {
          $html_markup = str_replace($before_markup, '', $html_markup);
        }
      }
    }
    // Once all empty regions have been removed, also remove the data attribute.
    $html_markup = str_replace('data-filter-empty-region', '', $html_markup);
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
