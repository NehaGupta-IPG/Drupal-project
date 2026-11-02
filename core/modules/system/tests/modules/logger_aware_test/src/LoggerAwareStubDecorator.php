<?php

declare(strict_types=1);

namespace Drupal\logger_aware_test;

use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Decorates a LoggerAwareStub without implementing LoggerAwareInterface.
 */
class LoggerAwareStubDecorator {

  public function __construct(
    #[AutowireDecorated]
    private readonly LoggerAwareStub $decorated,
  ) {}

  /**
   * Gets the decorated service.
   */
  public function getDecorated(): LoggerAwareStub {
    return $this->decorated;
  }

}
