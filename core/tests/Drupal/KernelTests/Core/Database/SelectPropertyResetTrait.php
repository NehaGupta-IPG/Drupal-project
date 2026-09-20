<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\Query\SelectExtender;
use Drupal\Core\Database\Query\SelectInterface;

/**
 * A trait helper to reset Select object properties for testing.
 */
trait SelectPropertyResetTrait {

  /**
   * Recursively resets 'uniqueIdentifier', 'range' properties.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $select
   *   The SelectInterface object to adjust.
   */
  protected function resetProperties(SelectInterface $select): void {
    $main = new \ReflectionProperty($select, 'uniqueIdentifier');
    $main->setValue($select, NULL);

    if (!$select instanceof SelectExtender) {
      $range = new \ReflectionProperty($select, 'range');
      if (is_null($range->getValue($select))) {
        $range->setValue($select, []);
      }
    }

    if ($select instanceof SelectExtender) {
      $extender = $select;
      $query = new \ReflectionProperty($extender, 'query');
      $select = $query->getValue($extender);
      $this->resetProperties($select);
    }

    $tables = new \ReflectionProperty($select, 'tables');
    $tablesArray = $tables->getValue($select);
    foreach ($tablesArray as $value) {
      if ($value['table'] instanceof SelectInterface) {
        $this->resetProperties($value['table']);
      }
    }
    $tables->setValue($select, $tablesArray);

    $condition = new \ReflectionProperty($select, 'condition');
    $selectCondition = $condition->getValue($select);
    $innerConditions = new \ReflectionProperty($selectCondition, 'conditions');
    $innerConditionsValue = $innerConditions->getValue($selectCondition);
    $tmp = [];
    foreach ($innerConditionsValue as $k => $c) {
      if (is_array($c)) {
        if (isset($c['field']) && $c['field'] instanceof SelectInterface) {
          $this->resetProperties($c['field']);
        }
        if (isset($c['value']) && $c['value'] instanceof SelectInterface) {
          $this->resetProperties($c['value']);
        }
        if (isset($c['value']) && is_array($c['value'])) {
          foreach ($c['value'] as $arrayValue) {
            if ($arrayValue instanceof SelectInterface) {
              $this->resetProperties($arrayValue);
            }
          }
        }
      }
      $tmp[$k] = $c;
    }
    $innerConditions->setValue($selectCondition, $tmp);

    $union = new \ReflectionProperty($select, 'union');
    $unionArray = $union->getValue($select);
    foreach ($unionArray as $value) {
      $this->resetProperties($value['query']);
    }
    $union->setValue($select, $unionArray);

    if (isset($extender) && isset($query)) {
      $query->setValue($extender, $select);
    }
  }

}
