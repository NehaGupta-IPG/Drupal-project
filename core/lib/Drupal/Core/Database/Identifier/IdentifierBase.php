<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Identifier;

/**
 * The base class for database identifier value objects.
 */
abstract class IdentifierBase implements \Stringable {

  public function __construct(
    public readonly string $identifier,
    public readonly string $canonicalName,
    public readonly string $machineName,
    public readonly string $quotedMachineName,
  ) {
  }

  /**
   * Returns the canonical name of the identifier.
   *
   * @return string
   *   The canonical name of the identifier.
   */
  public function canonical(): string {
    return $this->canonicalName;
  }

  /**
   * Returns the identifier in a format suitable for including in SQL queries.
   *
   * @return string
   *   The identifier in a format suitable for including in SQL queries.
   */
  public function forMachine(): string {
    return $this->quotedMachineName;
  }

  /**
   * Returns the identifier in a format suitable for including in SQL queries.
   *
   * @return string
   *   The identifier in a format suitable for including in SQL queries.
   */
  public function __toString(): string {
    return $this->forMachine();
  }

}
