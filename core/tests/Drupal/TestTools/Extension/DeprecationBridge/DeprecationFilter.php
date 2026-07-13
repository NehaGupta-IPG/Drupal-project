<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge;

use PHPUnit\Event\Code\IssueTrigger\IssueTrigger;
use PHPUnit\Runner\DeprecationFilter as DeprecationFilterInterface;

/**
 * Drupal's PHPUnit deprecation filter.
 *
 * @internal
 */
final class DeprecationFilter implements DeprecationFilterInterface {

  /**
   * {@inheritdoc}
   */
  public function ignores(string $message, string $file, int $line, IssueTrigger $trigger): bool {
    if (DeprecationHandler::isIgnoredByProject($message)) {
      return TRUE;
    }
    return FALSE;
  }

}
