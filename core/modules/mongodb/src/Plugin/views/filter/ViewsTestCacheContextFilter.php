<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views_test_data\Plugin\views\filter\ViewsTestCacheContextFilter as CoreViewsTestCacheContextFilter;

/**
 * Overriding the views filter plugin "views_test_test_cache_context".
 */
class ViewsTestCacheContextFilter extends CoreViewsTestCacheContextFilter {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->value = \Drupal::state()->get('views_test_cache_context', 'George');

    $this->ensureMyTable();
    if ($this->table == $this->view->storage->get('base_table')) {
      $field = $this->realField;
    }
    else {
      $field = "$this->tableAlias.$this->realField";
    }
    $this->query->addCondition($this->options['group'], $field, $this->value, $this->operator);
  }

}
