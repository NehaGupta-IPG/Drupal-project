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

  /**
   * Returns whether the front controller is percent-encoded in the request.
   *
   * Symfony may treat a request to /index%2Ephp/path as if the site lives in a
   * subdirectory named "index.php". That encoded form is never a legitimate
   * unclean-URL configuration.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return bool
   *   TRUE if the request path contains a percent-encoded front controller.
   */
  public static function hasEncodedFrontController(Request $request) {
    $script_name = rawurldecode($request->getScriptName());
    if ($script_name === '') {
      return FALSE;
    }
    $uri = Uri::parse($request->getRequestUri());
    if ($uri === NULL) {
      return FALSE;
    }
    $path = $uri->getRawPath();
    if ($path === '' || $path === rawurldecode($path)) {
      return FALSE;
    }
    $decoded_path = rawurldecode($path);
    return $decoded_path === $script_name || str_starts_with($decoded_path, $script_name . '/');
  }

  /**
   * Returns the base URL that should be used when generating links.
   *
   * A percent-encoded front controller is stripped so generated links are not
   * poisoned. Literal /index.php requests are left unchanged so sites without
   * clean URLs keep working.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string
   *   The base URL to prepend to generated paths, without a trailing slash.
   */
  public static function getGeneratedBaseUrl(Request $request) {
    if (!static::hasEncodedFrontController($request)) {
      return $request->getBaseUrl();
    }
    $real_base = dirname(rawurldecode($request->getScriptName()));
    return ($real_base === '/' || $real_base === '\\') ? '' : $real_base;
  }

  /**
   * Returns the base path that should be used when generating unrouted URLs.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string
   *   The base path including a trailing slash.
   */
  public static function getGeneratedBasePath(Request $request) {
    if (static::hasEncodedFrontController($request)) {
      $generated_base = static::getGeneratedBaseUrl($request);
      return ($generated_base === '' ? '' : $generated_base) . '/';
    }
    return $request->getBasePath() . '/';
  }

}
