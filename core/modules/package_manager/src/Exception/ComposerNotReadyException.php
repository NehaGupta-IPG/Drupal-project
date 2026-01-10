<?php

declare(strict_types=1);

namespace Drupal\package_manager\Exception;

/**
 * Exception thrown if we cannot reliably use Composer.
 *
 * Should not be thrown by external code.
 *
 * @see \Drupal\package_manager\ComposerInspector::validate()
 */
final class ComposerNotReadyException extends \RuntimeException {

  public function __construct(public readonly ?string $workingDir, array $messages, int $code = 0, ?\Throwable $previous = NULL) {
    parent::__construct(implode("\n", $messages), $code, $previous);
  }

}
