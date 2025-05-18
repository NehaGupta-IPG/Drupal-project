<?php

namespace Drupal\Core\Exception;

/**
 * Exception that can be thrown when and invalid argument is passed.
 *
 * This extends the PHP InvalidArgumentException by allowing for error codes to
 * be set as enums.
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionWithEnumCodeInterface {

  /**
   * Constructs an InvalidArgumentException.
   *
   * @param string $message
   *   The error message.
   * @param \Drupal\Core\Exception\ExceptionCodeInterface|null $enumCode
   *   (optional) Enum representation of the error code.
   * @param int|null $code
   *   (optional) The error code. If NULL is passed, then the integer error code
   *   will be derived from the enum code, if there is one. Otherwise, the error
   *   code will 0.
   * @param \Throwable|null $previous
   *   (optional) The previous throwable used for the exception chaining.
   */
  public function __construct(string $message = "", protected readonly ?ExceptionCodeInterface $enumCode = NULL, ?int $code = NULL, ?\Throwable $previous = NULL) {
    $code = $code ?? ($enumCode?->getCode() ?? 0);
    parent::__construct($message, $code, $previous);
  }

  /**
   * {@inheritdoc}
   */
  public function getEnumExceptionCode(): ?ExceptionCodeInterface {
    return $this->enumCode;
  }

}
