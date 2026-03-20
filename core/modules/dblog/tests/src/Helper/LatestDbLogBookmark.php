<?php

declare(strict_types=1);

namespace Drupal\Tests\dblog\Helper;

/**
 * A special exception type used as a bookmark in dblog reader.
 *
 * This provides a stack trace in test failure output for an unexpected log
 * message, that is closer to the operation that caused that log message.
 *
 * This exception should never be thrown directly, instead it is kept for later,
 * and then used as a "previous" exception.
 *
 * @see \Drupal\Tests\dblog\Helper\TestDbLogReader
 */
class LatestDbLogBookmark extends \Exception {

}
