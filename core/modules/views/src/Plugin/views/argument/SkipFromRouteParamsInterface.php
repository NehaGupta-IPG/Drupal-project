<?php

namespace Drupal\views\Plugin\views\argument;

/**
 * Provides an interface for skipping arguments from route params.
 */
interface SkipFromRouteParamsInterface {

  /**
   * Determines whether the contextual argument should be skipped.
   *
   * @return bool
   *   Returns true if the contextual argument should be skipped otherwise,
   *   returns false.
   */
  public function skipFromRouteParams(): bool;

}
