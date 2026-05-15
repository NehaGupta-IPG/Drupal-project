<?php

declare(strict_types=1);

namespace Drupal\TestTools\TestRunner;

/**
 * Enumeration of the possible levels of a run-tests.sh log entry.
 *
 * @internal
 */
enum LogLevel {

  case Info;
  case Deprecation;

}
