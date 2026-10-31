<?php

namespace Drupal\jsonapi\Exception;

/**
 * Value object for JSON:API error object source values.
 *
 * @see https://jsonapi.org/format/1.1/#error-objects
 */
final class JsonApiErrorSource {

  /**
   * Constructor.
   *
   * @param string|null $pointer
   *   JSON pointer.
   * @param string|null $parameter
   *   Query parameter.
   * @param string|null $header
   *   Header name.
   */
  public function __construct(
    protected readonly ?string $pointer = NULL,
    protected readonly ?string $parameter = NULL,
    protected readonly ?string $header = NULL
  ) {
    if (count(array_filter(func_get_args())) !== 1) {
      throw new \InvalidArgumentException('Only one of $pointer, $parameter, or $header may be provided.');
    }
  }

  /**
   * Return the primary source of the error as a value for 'source'.
   *
   * @return array
   */
  public function toArray(): array {
    return array_filter([
      'pointer' => $this->pointer,
      'parameter' => $this->parameter,
      'header' => $this->header,
    ]);
  }

}
