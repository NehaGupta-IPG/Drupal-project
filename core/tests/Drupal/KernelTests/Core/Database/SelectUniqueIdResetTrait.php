<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\Query\SelectExtender;
use Drupal\Core\Database\Query\SelectInterface;

/**
 * A trait helper to reset Select object unique identifiers.
 */
trait SelectUniqueIdResetTrait {

  protected function resetUniqueIdentifiers(SelectInterface $select): void {
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
      $this->resetUniqueIdentifiers($select);
    }

    $tables = new \ReflectionProperty($select, 'tables');
    $tablesArray = $tables->getValue($select);
    foreach ($tablesArray as $key => $value) {
      if ($value['table'] instanceof SelectInterface) {
        $this->resetUniqueIdentifiers($value['table']);
      }
    }
    $tables->setValue($select, $tablesArray);

    $union = new \ReflectionProperty($select, 'union');
    $unionArray = $union->getValue($select);
    foreach ($unionArray as $key => $value) {
      $this->resetUniqueIdentifiers($value['query']);
    }
    $union->setValue($select, $unionArray);

    if (isset($extender)) {
      $query->setValue($extender, $select);
    }
  }

}
