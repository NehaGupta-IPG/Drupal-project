<?php

declare(strict_types=1);

namespace Drupal\Core\Exception;

/**
 * Interface for an exception that allow enums to be used as error codes.
 */
interface ExceptionWithEnumCodeInterface extends \Throwable {

  /**
   * Gets the enum exception code.
   *
   * @return \Drupal\Core\Exception\ExceptionCodeInterface|null
   *   The enum code if it exists, otherwise NULL.
   */
  public function getEnumExceptionCode(): ?ExceptionCodeInterface;

}
