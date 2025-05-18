<?php

declare(strict_types=1);

namespace Drupal\Core\Exception;

/**
 * Interface for enum that returns an integer error code for its cases.
 */
interface ExceptionCodeInterface {

  /**
   * Gets the exception code associated with the enum case.
   *
   * @return int
   *   The exception code.
   */
  public function getCode(): int;

}
