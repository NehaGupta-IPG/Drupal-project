<?php

namespace Drupal\Core\Environment;

/**
 * Data object encapsulating the parameters for an environment substitution.
 */
final class EnvironmentParameter {

  /**
   * Constructs new data object with environment substitution parameter.
   *
   * @param string $name
   *   Parameter name. By convention this is the fully qualified class name of
   *   an environment substitution service.
   * @param bool|int|float|string|null $value
   *   Parameter value.
   */
  public function __construct(
    public readonly string $name,
    public readonly bool|int|float|string|null $value = NULL
  ) {}

}
