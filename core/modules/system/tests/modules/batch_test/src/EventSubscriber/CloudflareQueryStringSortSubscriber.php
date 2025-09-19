<?php

declare(strict_types=1);

namespace Drupal\batch_test\EventSubscriber;

use Symfony\Component\HttpFoundation\RedirectResponse;
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

    $query_string = $request->getQueryString();

    // Only process if there's a query string to sort.
    if (!$query_string) {
      return;
    }

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

    $sorted_query_string = implode('&', $sorted_parts);

    // If the query string changed, redirect to the sorted version.
    if ($sorted_query_string !== $query_string) {
      $path = $request->getPathInfo();
      $redirect_url = $request->getUriForPath($path) . '?' . $sorted_query_string;
      $response = new RedirectResponse($redirect_url);
      $event->setResponse($response);
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

}
