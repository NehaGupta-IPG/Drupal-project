<?php

declare(strict_types=1);

namespace Drupal\batch_test\EventSubscriber;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Simulates Cloudflare's "Query String Sort" feature for testing.
 *
 * This subscriber intercepts requests early and sorts query string parameters
 * by name in ascending order. For parameters with the same name, it sorts by
 * their values in ascending order.
 */
class CloudflareQueryStringSortSubscriber implements EventSubscriberInterface {

  /**
   * Sorts query string parameters to simulate Cloudflare's behavior.
   *
   * Only applies to system.batch_page.json routes.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The RequestEvent to process.
   */
  public function sortQueryString(RequestEvent $event): void {
    $request = $event->getRequest();

    // Only process requests for the system.batch_page.json route.
    if ($request->getPathInfo() !== '/batch' || $request->getRequestFormat() !== 'json') {
      return;
    }

    $query_string = $this->getQueryStringFromRequestUri($request);

    // Only process if there's a query string to sort.
    if (!$query_string) {
      return;
    }

    $sorted_query_string = $this->getSortedQueryString($request, $query_string);

    if ($sorted_query_string !== $query_string) {
      $this->updateRequestQueryString($request, $sorted_query_string);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Use high priority to ensure this runs early in the request cycle.
    $events[KernelEvents::REQUEST][] = ['sortQueryString', 1000];
    return $events;
  }

  /**
   * Updates the query string in the request object.
   *
   * This method updates both the query parameters and server variables to
   * reflect the new query string. It modifies:
   * - The request's query parameters.
   * - The QUERY_STRING server variable.
   * - The REQUEST_URI server variable.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object to modify.
   * @param string $new_query_string
   *   The new query string to set.
   */
  protected function updateRequestQueryString(Request $request, string $new_query_string): void {
    parse_str($new_query_string, $params);

    $request->query->replace($params);
    $request->server->set('QUERY_STRING', $new_query_string);

    $pathInfo = $request->getPathInfo();
    $new_request_uri = $pathInfo . ($new_query_string ? '?' . $new_query_string : '');
    $request->server->set('REQUEST_URI', $new_request_uri);
  }

  /**
   * Extracts the query string from a request's URI.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request object to extract the query string from.
   *
   * @return string
   *   The query string portion of the URI, or an empty string if no query
   *   string exists.
   */
  protected function getQueryStringFromRequestUri(Request $request): string {
    $request_uri = $request->getRequestUri();
    return parse_url($request_uri, PHP_URL_QUERY);
  }

  /**
   * Sorts query string parameters in ascending order by name and value.
   *
   * This method parses the query string and sorts parameters according to
   * Cloudflare's Query String Sort behavior:
   * - Parameters are primarily sorted by name in ascending order.
   * - Parameters with the same name are sorted by their values.
   * - Array parameters (ending with []) are preserved with their array
   *   notation.
   * - Parameters without values are maintained as valueless in the output.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request object containing the query parameters.
   * @param string $query_string
   *   The raw query string to be sorted.
   *
   * @return string
   *   The sorted query string with encoded parameter names and values.
   */
  protected function getSortedQueryString(Request $request, string $query_string): string {
    // Parse the query string manually to preserve duplicate parameter names.
    $params = [];
    $pairs = explode('&', $query_string);

    foreach ($pairs as $pair) {
      if (str_contains($pair, '=')) {
        [$name, $value] = explode('=', $pair, 2);
        $name = urldecode($name);
        $value = urldecode($value);

        // Check if this is an array parameter.
        if (str_ends_with($name, '[]')) {
          $base_name = substr($name, 0, -2);
          $params[] = ['name' => $base_name, 'value' => $value, 'is_array' => TRUE];
        }
        else {
          $params[] = ['name' => $name, 'value' => $value, 'is_array' => FALSE];
        }
      }
      else {
        // Handle parameters without values.
        $name = urldecode($pair);
        $params[] = ['name' => $name, 'value' => '', 'is_array' => FALSE];
      }
    }

    // Sort parameters by name first, then by value.
    usort($params, function ($a, $b) {
      $name_comparison = strcmp($a['name'], $b['name']);
      if ($name_comparison === 0) {
        return strcmp($a['value'], $b['value']);
      }
      return $name_comparison;
    });

    // Rebuild the query string with sorted parameters.
    $sorted_parts = [];
    foreach ($params as $param) {
      if ($param['is_array']) {
        $sorted_parts[] = urlencode($param['name']) . '[]=' . urlencode($param['value']);
      }
      else {
        if ($param['value'] === '') {
          $sorted_parts[] = urlencode($param['name']);
        }
        else {
          $sorted_parts[] = urlencode($param['name']) . '=' . urlencode($param['value']);
        }
      }
    }

    return implode('&', $sorted_parts);
  }

}
