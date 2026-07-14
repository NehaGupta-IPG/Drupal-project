<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge;

use PHPUnit\Event\Code\IssueTrigger\IssueTrigger;
use PHPUnit\Runner\DeprecationFilter as DeprecationFilterInterface;
use PHPUnit\TextUI\Configuration\Registry as PhpunitConfigurationRegistry;

/**
 * Drupal's PHPUnit deprecation filter.
 *
 * @internal
 */
final class DeprecationFilter implements DeprecationFilterInterface {

  /**
   * The extension configuration.
   */
  private Configuration $config;

  public function __construct() {
    // Get deprecation override handling as early as possible, depending on the
    // extension configuration.
    DeprecationHandler::preBootstrap(PhpunitConfigurationRegistry::get());
    $this->config = Configuration::instance();
  }

  /**
   * {@inheritdoc}
   */
  public function ignores(string $message, string $file, int $line, IssueTrigger $trigger): bool {
    // Deprecations that match the patterns included in the ignore file should
    // be ignored.
    foreach ($this->config->deprecationIgnorePatterns as $pattern) {
      $result = @preg_filter($pattern, '$0', $message);
      if (preg_last_error() !== \PREG_NO_ERROR) {
        throw new \RuntimeException(preg_last_error_msg());
      }
      if ((bool) $result) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
