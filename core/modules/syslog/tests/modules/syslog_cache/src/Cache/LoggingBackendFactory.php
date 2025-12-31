<?php

declare(strict_types=1);

namespace Drupal\syslog_cache\Cache;

use Drupal\Core\Cache\NullBackendFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Defines a logging cache backend factory.
 */
class LoggingBackendFactory extends NullBackendFactory {

  public function __construct(protected LoggerChannelFactoryInterface $loggerFactory) {
  }

}
