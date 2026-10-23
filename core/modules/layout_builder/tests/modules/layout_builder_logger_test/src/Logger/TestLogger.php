<?php

declare(strict_types=1);

namespace Drupal\layout_builder_logger_test\Logger;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;

/**
 * A logger that depends on the cache tags invalidator.
 */
class TestLogger implements LoggerInterface {

  use RfcLoggerTrait;

  public function __construct(
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
  }

}
