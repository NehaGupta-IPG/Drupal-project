<?php

namespace Drupal\Core\Boot;

/**
 * Places where parts of Drupal can live.
 *
 * The values of the enum are parameters in the pre bootstrap container except
 * for app_root which is only used by PreBootstrap::pathToOther().
 */
enum PreBootstrapPath: string {

  /**
   * Relative path to the app root.
   */
  case APP_ROOT = 'app_root';

  /**
   * The path to the vendor directory relative from the app root.
   */
  case VENDOR = 'vendor_dir';

  /**
   * Relative path to drupal/core from the app root.
   */
  case CORE = 'drupal_core_path';

  /**
   * Relative path to the web_root from the app root.
   */
  case WEB = 'web_root';

}
