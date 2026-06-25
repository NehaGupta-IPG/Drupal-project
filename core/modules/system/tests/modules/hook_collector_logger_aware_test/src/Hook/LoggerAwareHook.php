<?php

declare(strict_types=1);

namespace Drupal\hook_collector_logger_aware_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Hook implementations for hook_collector_logger_aware_test.
 *
 * Implements LoggerAwareInterface so that the logger is injected automatically
 * via Symfony's autoconfiguration pipeline (registerForAutoconfiguration in
 * CoreServiceProvider applies the logger_aware tag, LoggerAwarePass then
 * calls setLogger()).
 */
#[Hook('cache_flush')]
class LoggerAwareHook implements LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * Implements hook_cache_flush().
   */
  public function __invoke(): void {
  }

  /**
   * Returns the injected logger.
   *
   * @return \Psr\Log\LoggerInterface|null
   *   The logger, or NULL if not yet injected.
   */
  public function getLogger(): ?LoggerInterface {
    return $this->logger;
  }

}
