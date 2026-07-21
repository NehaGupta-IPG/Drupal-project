<?php

declare(strict_types=1);

namespace Drupal\logger_aware_test;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Decorates a LoggerAwareStub while being logger aware itself.
 *
 * Used to test that a decoration chain with more than one
 * LoggerAwareInterface implementor gets every instance wired up, not just
 * the first one found.
 */
class LoggerAwareMiddleDecorator implements LoggerAwareInterface {

  use LoggerAwareTrait;

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

  /**
   * Gets the logger.
   */
  public function getLogger(): LoggerInterface {
    return $this->logger;
  }

}
