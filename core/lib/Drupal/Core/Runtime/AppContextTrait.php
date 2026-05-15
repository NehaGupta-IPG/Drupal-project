<?php

declare(strict_types=1);

namespace Drupal\Core\Runtime;

use Drupal\Core\AppContext;
use Drupal\Core\AppContextInterface;
use Drupal\Core\Test\TestDatabase;
use Drupal\DrupalInstalled;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides methods to determine the app context for a runtime.
 *
 * This trait should not be used unless you're building a custom runtime for
 * Drupal. The information gathered by this trait should be injected into your
 * class or services through the runtime.
 */
trait AppContextTrait {

  /**
   * Get the application context for a request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request being served.
   * @param bool $enable_multisite
   *   Whether Drupal multi-site support should be enabled.
   *
   * @return \Drupal\Core\AppContextInterface
   *   The application context.
   */
  protected function getAppContext(Request $request, bool $enable_multisite = TRUE) : AppContextInterface {
    $app_root = $this->getApplicationRoot();
    // @todo BEFORE MERGE TO MAIN Because this is now routed to InstallCommand it should respect DRUPAL_DEV_SITE_PATH.
    $site_path = "sites/default";
    if ($enable_multisite) {
      $site_path = $this->findSitePath($request, $app_root);
    }

    [
      'base_path' => $base_path,
      'base_url' => $base_url,
    ] = $this->getBaseUrlAndPath($request);

    return new AppContext(
      $app_root,
      $site_path,
      $base_url,
      $base_path
    );
  }

  /**
   * Get the application context for a request with a fixed site path.
   *
   * Primarily useful in tests where the site path is pre-determined.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request being served.
   * @param string $site_path
   *   The site path that should be used.
   *
   * @return \Drupal\Core\AppContextInterface
   *   The application context.
   */
  protected function getAppContextWithSitePath(Request $request, string $site_path) : AppContextInterface {
    $app_root = $this->getApplicationRoot();

    [
      'base_path' => $base_path,
      'base_url' => $base_url,
    ] = $this->getBaseUrlAndPath($request);

    return new AppContext(
      $app_root,
      $site_path,
      $base_url,
      $base_path
    );
  }

  /**
   * Returns the application root directory.
   *
   * This uses the constant written in \Drupal\Locations\DrupalLocation by the
   * drupal/core-composer-scaffold Composer plugin.
   *
   * For backwards compatibility with installations that do not use Composer,
   * the fallback is to determine the app root from this file's location, which
   * is not always reliable in a nonstandard installation structure.
   *
   * @return string
   *   The application root.
   *
   * @see \Drupal\Composer\Plugin\Scaffold\GenerateAutoloadReferenceFile
   * @see https://www.drupal.org/i/1792310
   */
  protected function getApplicationRoot() : string {
    // Forward compatibility with https://www.drupal.org/i/1792310.
    if (class_exists(DrupalInstalled::class) && method_exists(DrupalInstalled::class, "getAppRoot")) {
      return DrupalInstalled::getAppRoot();
    }
    // Determine the application root by:
    // - Removing the namespace directories from the path.
    // - Getting the path to the directory two levels up from the path
    //   determined in the previous step.
    return dirname(substr(__DIR__, 0, -strlen(__NAMESPACE__)), 2);
  }

  /**
   * Returns the appropriate site directory for a request.
   *
   * Once the kernel has been created DrupalKernelInterface::getSitePath() is
   * preferred since it gets the statically cached result of this method.
   *
   * Site directories contain all site specific code. This includes settings.php
   * for bootstrap level configuration, file configuration stores, public file
   * storage and site specific modules and themes.
   *
   * A file named sites.php must be present in the sites directory for
   * multisite. If it doesn't exist, then 'sites/default' will be used.
   *
   * Finds a matching site directory file by stripping the website's hostname
   * from left to right and pathname from right to left. By default, the
   * directory must contain a 'settings.php' file for it to match. If the
   * parameter $require_settings is set to FALSE, then a directory without a
   * 'settings.php' file will match as well. The first configuration file found
   * will be used and the remaining ones will be ignored. If no configuration
   * file is found, returns a default value 'sites/default'. See
   * default.settings.php for examples on how the URL is converted to a
   * directory.
   *
   * The sites.php file in the sites directory can define aliases in an
   * associative array named $sites. The array is written in the format
   * '<port>.<domain>.<path>' => 'directory'. As an example, to create a
   * directory alias for https://www.drupal.org:8080/my-site/test whose
   * configuration file is in sites/example.com, the array should be defined as:
   *
   * @code
   * $sites = [
   *   '8080.www.drupal.org.my-site.test' => 'example.com',
   * ];
   * @endcode
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $app_root
   *   The path to the application root as a string. If not supplied, the
   *   application root will be computed.
   * @param bool $require_settings
   *   Only directories with an existing settings.php file will be recognized.
   *   Defaults to TRUE. During initial installation, this is set to FALSE so
   *   that Drupal can detect a matching directory, then create a new
   *   settings.php file in it.
   *
   * @return string
   *   The path of the matching directory.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   In case the host name in the request is invalid.
   *
   * @see \Drupal\Core\DrupalKernelInterface::getSitePath()
   * @see \Drupal\Core\DrupalKernelInterface::setSitePath()
   * @see default.settings.php
   * @see example.sites.php
   */
  protected function findSitePath(Request $request, string $app_root, bool $require_settings = TRUE) {
    if ($this->validateHostname($request) === FALSE) {
      throw new BadRequestHttpException();
    }

    // Check for a test override.
    if ($test_prefix = drupal_valid_test_ua()) {
      $test_db = new TestDatabase($test_prefix);
      return $test_db->getTestSitePath();
    }

    // Determine whether multi-site functionality is enabled. If not, return
    // the default directory.
    if (!is_file($app_root . '/sites/sites.php')) {
      return 'sites/default';
    }

    // Pre-populate host and script variables, then include sites.php which may
    // populate $sites with a site-directory mapping.
    $script_name = $request->server->get('SCRIPT_NAME');
    if (!$script_name) {
      $script_name = $request->server->get('SCRIPT_FILENAME');
    }
    $http_host = $request->getHttpHost();

    $sites = [];
    include $app_root . '/sites/sites.php';

    // Construct an identifier from pieces of the (port plus) host plus script
    // path (excluding the filename). Loop over all possibilities starting from
    // most specific, then dropping pieces from the start of the port/hostname
    // while keeping the full path, then gradually dropping pieces from the end
    // of the path... until we find a directory corresponding to the identifier.
    $path_parts = explode('/', $script_name);
    $host_parts = explode('.', implode('.', array_reverse(explode(':', rtrim($http_host, '.')))));
    for ($i = count($path_parts) - 1; $i > 0; $i--) {
      for ($j = count($host_parts); $j > 0; $j--) {
        // Assume the path has a leading slash, so the imploded path parts are
        // either a path identifier with leading dot, or an empty string.
        $site_id = implode('.', array_slice($host_parts, -$j)) . implode('.', array_slice($path_parts, 0, $i));

        // If the identifier is a key in $sites, check for a directory matching
        // the corresponding value. Otherwise, check for a directory matching
        // the identifier.
        if (isset($sites[$site_id]) && is_dir($app_root . '/sites/' . $sites[$site_id])) {
          $site_id = $sites[$site_id];
        }
        if (is_file($app_root . '/sites/' . $site_id . '/settings.php') || (!$require_settings && is_file($app_root . '/sites/' . $site_id))) {
          return "sites/$site_id";
        }
      }
    }
    return 'sites/default';
  }

  /**
   * Validates the hostname supplied from the HTTP request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return bool
   *   TRUE if the hostname is valid, or FALSE otherwise.
   */
  protected function validateHostname(Request $request) {
    // $request->getHost() can throw an UnexpectedValueException if it
    // detects a bad hostname, but it does not validate the length.
    try {
      $http_host = $request->getHost();
    }
    catch (\UnexpectedValueException) {
      return FALSE;
    }

    if ($this->validateHostnameLength($http_host) === FALSE) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Validates a hostname length.
   *
   * @param string $host
   *   A hostname.
   *
   * @return bool
   *   TRUE if the length is appropriate, or FALSE otherwise.
   */
  private function validateHostnameLength($host) {
    // Limit the length of the host name to 1000 bytes to prevent DoS attacks
    // with long host names.
    return strlen($host) <= 1000
      // Limit the number of subdomains and port separators to prevent DoS
      // attacks in findSitePath().
      && substr_count($host, '.') <= 100
      && substr_count($host, ':') <= 100;
  }

  /**
   * Get the base URL and path from a request.
   *
   * Ensures that the returned base URL and path are according to Drupal's
   * conventions, where these concepts differ slightly from Symfony's request
   * object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The symfony request.
   *
   * @return array{base_url: string, base_path: string}
   *   The URL and path information as an associative array.
   */
  protected function getBaseUrlAndPath(Request $request) : array {
    // Create base URL.
    $base_root = $request->getSchemeAndHttpHost();
    $base_url = $base_root;

    // For a request URI of '/index.php/foo', $_SERVER['SCRIPT_NAME'] is
    // '/index.php', whereas $_SERVER['PHP_SELF'] is '/index.php/foo'.
    if ($dir = rtrim(dirname($request->server->get('SCRIPT_NAME')), '\/')) {
      // Remove "core" directory if present, allowing install.php, rebuild.php,
      // and others to auto-detect a base path.
      $core_position = strrpos($dir, '/core');
      if ($core_position !== FALSE && strlen($dir) - 5 == $core_position) {
        $base_path = substr($dir, 0, $core_position);
      }
      else {
        $base_path = $dir;
      }
      $base_url .= $base_path;
      $base_path .= '/';
    }
    else {
      $base_path = '/';
    }

    return [
      'base_path' => $base_path,
      'base_url' => $base_url,
    ];
  }

  /**
   * Wraps the PHP realpath for testing purposes.
   *
   * @param string $path
   *   The path to be converted.
   *
   * @return string
   *   The true path with symlinks, and relative portions, resolved.
   */
  private function realpath(string $path): string {
    return realpath($path);
  }

}
