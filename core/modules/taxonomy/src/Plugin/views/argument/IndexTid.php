<?php

namespace Drupal\taxonomy\Plugin\views\argument;

use Drupal\taxonomy\Entity\Term;
use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\ManyToOne;

/**
 * Allow taxonomy term ID(s) as argument.
 *
 * @ingroup views_argument_handlers
 */
#[ViewsArgument(
  id: 'taxonomy_index_tid',
)]
class IndexTid extends ManyToOne {

  /**
   * {@inheritdoc}
   */
  public function titleQuery() {
    $titles = [];
    $terms = Term::loadMultiple($this->value);
    foreach ($terms as $term) {
      $titles[] = \Drupal::service('entity.repository')->getTranslationFromContext($term)->label();
    }
    return $titles;
  }

  /**
   * {@inheritdoc}
   */
  public function summaryName($data): string {
    $tid = $data->{$this->name_alias};
    $term = $tid ? Term::load($tid) : NULL;
    if ($term) {
      return \Drupal::service('entity.repository')->getTranslationFromContext($term)->label();
    }
    return parent::summaryName($data);
  }

}
