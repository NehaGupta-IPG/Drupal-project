<?php

declare(strict_types=1);

namespace Drupal\Core\Config;

/**
 * Identifies why a configuration langcode is being resolved.
 *
 * Subscribers to \Drupal\Core\Config\ConfigEvents::RESOLVE_LANGCODE use this
 * to apply different rules depending on when the configuration is being saved.
 */
enum ConfigLangcodeContext {

  // Configuration is being saved at runtime.
  case Runtime;

  // Default configuration shipped by an extension is being installed.
  case ExtensionInstall;

}
