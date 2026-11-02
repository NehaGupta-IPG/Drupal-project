<?php

namespace Drupal\Core\Routing;

use Symfony\Component\HttpFoundation\Request;
use Uri\Rfc3986\Uri;

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
    // The base URL may be percent-encoded, for example "index%2Ephp".
    // Comparing that raw base URL to the SCRIPT_NAME "index.php" would result
    // in a false-positive. Decode the base URL and script name before
    // comparison.
    $script_name = rawurldecode($request->getScriptName());
    $base_url = rawurldecode($request->getBaseUrl());
    if ($base_url !== '' && $script_name !== '' && str_contains($base_url, $script_name)) {
      return FALSE;
    }
    if ($script_name === '' || $script_name === '/') {
      return $base_url === '';
    }
    // Symfony may omit an encoded front controller from the base URL. Check
    // the decoded request path as well.
    $path = Uri::parse($request->getRequestUri())?->getPath() ?? '';
    return $path !== $script_name && !str_starts_with($path, $script_name . '/');
  }

}
