<?php

namespace Drupal\Core\Condition;

/**
 * Provides a base class for condition plugins that allows negation.
 *
 * @see \Drupal\Core\Condition\ConditionPluginBase
 * @see \Drupal\Core\Condition\NegatableConditionInterface
 * @see \Drupal\Core\Condition\ConditionManager
 *
 * @ingroup plugin_api
 */
abstract class NegatableConditionPluginBase extends ConditionPluginBase implements NegatableConditionInterface {

  /**
   * {@inheritdoc}
   */
  public function evaluateNegate(bool $result): bool {
    return $this->isNegated() ? !$result : $result;
  }

}
