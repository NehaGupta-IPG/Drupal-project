<?php

namespace Drupal\Core\Condition;

/**
 * An interface for condition plugins that allow negation.
 *
 * @see \Drupal\Core\Condition\ConditionInterface
 *
 * @ingroup plugin_api
 */
interface NegatableConditionInterface extends ConditionInterface {

  /**
   * Negate the condition result.
   *
   * @param bool $result
   *   The result from a previous condition that is to be negated.
   *
   * @return bool
   *   TRUE if the result is FALSE, FALSE otherwise.
   */
  public function evaluateNegate(bool $result): bool;

}
