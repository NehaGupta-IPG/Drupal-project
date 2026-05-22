<?php

namespace Drupal\mongodb\modules\dblog;

use Drupal\dblog\Logger\DbLog as CoreDbLog;

/**
 * The MongoDB implementation of \Drupal\dblog\Logger\DbLog.
 */
class DbLog extends CoreDbLog {

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    // MongoDB does not automatically transform an object to a string on insert.
    if (is_object($context['link'])) {
      $context['link'] = $context['link']->__toString();
    }

    parent::log($level, $message, $context);
  }

}
