<?php

declare(strict_types=1);

namespace Drupal\Core;

use Drupal\Core\Runtime\AppContextTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Makes AppContextTrait available statically for backwards compatibility.
 *
 * There's no need for the AppContextTrait methods to be available statically,
 * but DrupalKernel exposes them in that manner. This class bridges that gap.
 *
 * @internal
 */
final class StaticAppContextProvider {

  use AppContextTrait {
    getAppContext as getAppContextBase;
    getApplicationRoot as getApplicationRootBase;
    findSitePath as findSitePathBase;
    getBaseUrlAndPath as getBaseUrlAndPathBase;
  }

  private static ?self $instance = NULL;

  private function __construct() {}

  /**
   * {@inheritdoc}
   */
  public static function getAppContext(?Request $request = NULL, bool $enable_multisite = TRUE) : AppContext {
    $request ??= Request::createFromGlobals();
    return self::getInstance()->getAppContextBase($request, $enable_multisite);
  }

  /**
   * {@inheritdoc}
   */
  public static function getApplicationRoot() : string {
    return self::getInstance()->getApplicationRootBase();
  }

  /**
   * {@inheritdoc}
   */
  public static function findSitePath(Request $request, $app_root = NULL, $require_settings = TRUE) : string {
    $app_root ??= self::getApplicationRoot();
    return self::getInstance()->findSitePathBase($request, $app_root, $require_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function getBaseUrlAndPath(Request $request) : array {
    return self::getInstance()->getBaseUrlAndPathBase($request);
  }

  /**
   * Get a singleton instance of this class.
   *
   * @return self
   *   The singleton instance.
   */
  private static function getInstance() : self {
    if (self::$instance === NULL) {
      self::$instance = new self();
    }
    return self::$instance;
  }

}
