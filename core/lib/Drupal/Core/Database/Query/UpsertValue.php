<?php

namespace Drupal\Core\Database\Query;

/**
 * A value object to handle values passed to an Upsert class.
 */
class UpsertValue {

  /**
   * Constructor.
   *
   * @param string|int|float|bool|array $insert
   *   The inserted value.
   * @param \Drupal\Core\Database\Query\Expression $update
   *   The expression to be used on the existing record if the insert failed.
   */
  public function __construct(
    public readonly string|int|float|bool|array $insert,
    public readonly Expression $update,
  ) {
  }

}
