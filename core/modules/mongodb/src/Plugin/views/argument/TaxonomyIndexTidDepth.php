<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\mongodb\modules\taxonomy\TaxonomyIndexDepthQueryTrait;
use Drupal\taxonomy\Plugin\views\argument\IndexTidDepth;

/**
 * Overriding the views argument plugin "taxonomy_index_tid_depth".
 */
class TaxonomyIndexTidDepth extends IndexTidDepth {

  use TaxonomyIndexDepthQueryTrait;

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();

    if (!empty($this->options['break_phrase'])) {
      $break = static::breakString($this->argument);
      if ($break->value === [-1]) {
        return FALSE;
      }
      $tids = $break->value;
    }
    else {
      $tids = $this->argument;
    }

    $this->addSubQueryJoin($tids);
  }

}
