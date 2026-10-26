<?php

declare(strict_types=1);

namespace Drupal\logger_aware_test;

/**
 * A decorator that does not implement LoggerAwareInterface.
 */
class LoggerAwareStubDecorator {

  public function __construct(
    public readonly LoggerAwareStub $inner,
  ) {}

}
