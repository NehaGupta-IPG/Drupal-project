<?php

namespace Drupal\taxonomy\Plugin\views\argument;

use Drupal\taxonomy\TaxonomyIndexDepthJoinQueryTrait;
use Drupal\views\Attribute\ViewsArgument;

/**
 * Argument handler for taxonomy terms with depth using a term list join.
 *
 * This handler expands the term hierarchy with one query per level and joins
 * the taxonomy index on the resulting flat list of term IDs. This can be
 * more performant than the union subquery used by the base handler.
 *
 * @ingroup views_argument_handlers
 */
#[ViewsArgument(
  id: 'taxonomy_index_tid_depth_join',
)]
class IndexTidDepthJoin extends IndexTidDepth {

  use TaxonomyIndexDepthJoinQueryTrait;

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE): void {
    $this->ensureMyTable();

    if (!empty($this->options['break_phrase'])) {
      $break = static::breakString($this->argument);
      if ($break->value === [-1]) {
        return;
      }
      $tids = $break->value;
    }
    else {
      $tids = $this->argument;
    }

    $this->addDepthJoinToQuery($tids);
  }

}
