<?php

declare(strict_types=1);

namespace Drupal\Core\Exception;

/**
 * Exception that can be thrown when and invalid argument is passed.
 *
 * This extends the PHP InvalidArgumentException by allowing for error codes to
 * be set as enums.
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionWithEnumCodeInterface {

  /**
   * Enum representation of the error code.
   *
   * @var \Drupal\Core\Exception\ExceptionCodeInterface|null
   */
  protected ?ExceptionCodeInterface $enumCode = NULL;

  /**
   * Constructs an InvalidArgumentException.
   *
   * @param string $message
   *   The error message.
   * @param \Drupal\Core\Exception\ExceptionCodeInterface|int $code
   *   The error code. If an ExceptionCodeInterface enum is passed, the integer
   *   code will be derived from the enum.
   * @param \Throwable|null $previous
   *   (optional) The previous throwable used for the exception chaining.
   */
  public function __construct(string $message = "", ExceptionCodeInterface|int $code = 0, ?\Throwable $previous = NULL) {
    if ($code instanceof ExceptionCodeInterface) {
      $this->enumCode = $code;
      $code = $this->enumCode->getCode();
    }
    parent::__construct($message, $code, $previous);
  }

  /**
   * {@inheritdoc}
   */
  public function getEnumExceptionCode(): ?ExceptionCodeInterface {
    return $this->enumCode;
  }

}
