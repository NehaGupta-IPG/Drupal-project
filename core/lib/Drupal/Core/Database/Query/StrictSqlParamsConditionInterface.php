<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query;

/**
 * Interface for conditions that support strict SQL parameter binding.
 */
interface StrictSqlParamsConditionInterface {

  /**
   * Whether this condition requires strict parameter binding.
   *
   * When TRUE, parameters are bound with their native PHP types instead of
   * as strings. This is needed for queries that compare values in JSON columns,
   * where type coercion would produce incorrect results.
   *
   * @return bool
   *   TRUE if strict parameter binding should be used, FALSE otherwise.
   */
  public function usesStrictParameters(): bool;

}
