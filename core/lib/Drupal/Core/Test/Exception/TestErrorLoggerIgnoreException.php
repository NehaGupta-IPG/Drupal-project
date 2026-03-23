<?php

namespace Drupal\Core\Test\Exception;

/**
 * An exception that the TestErrorLogger will ignore.
 *
 * Useful for testing HTML output after triggering an exception in the system
 * under test.
 *
 * @see \Drupal\Core\Test\TestErrorLogger
 */
class TestErrorLoggerIgnoreException extends \Exception {
}
