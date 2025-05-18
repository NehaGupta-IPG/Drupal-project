<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Exception;

use Drupal\Core\Exception\ExceptionCodeInterface;

/**
 * Enum providing cases for exceptions thrown on invalid fields.
 */
enum InvalidArgumentCode implements ExceptionCodeInterface {

  case RemovedTranslation;
  case UnknownField;

  /**
   * {@inheritdoc}
   */
  public function getCode(): int {
    return 0;
  }

}
