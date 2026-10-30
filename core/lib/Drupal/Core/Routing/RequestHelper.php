<?php

namespace Drupal\Core\Routing;

use Symfony\Component\HttpFoundation\Request;

/**
 * Provides some helper methods for dealing with the request.
 */
class RequestHelper {

  /**
   * Returns whether the request is using a clean URL.
   *
   * A clean URL is one that does not include the script name. For example,
   * - https://example.com/node/1 is a clean URL.
   * - https://example.com/index.php/node/1 is not a clean URL.
   *
   * Percent-encoded variants of the front controller, such as index%2Ephp, are
   * treated as the front controller. Comparing the raw base URL to SCRIPT_NAME
   * misses those requests, so generated links and modules that call this method
   * (for example Redirect) would treat them as clean URLs.
   *
   * Unclean URLs are required on sites hosted by web servers that cannot be
   * configured to implicitly route URLs to index.php.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return bool
   *   TRUE if the request is using a clean URL.
   */
  public static function isCleanUrl(Request $request) {
    $script_name = rawurldecode($request->getScriptName());
    $base_url = rawurldecode($request->getBaseUrl());
    if ($base_url !== '' && $script_name !== '' && str_contains($base_url, $script_name)) {
      return FALSE;
    }
    if ($script_name === '' || $script_name === '/') {
      return $base_url === '';
    }
    $path = rawurldecode((string) parse_url($request->getRequestUri(), PHP_URL_PATH));
    return $path !== $script_name && !str_starts_with($path, $script_name . '/');
  }

}
