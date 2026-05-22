<?php

namespace Drupal\mongodb\Plugin\views\row;

use Drupal\node\Plugin\views\row\Rss;

/**
 * Overrides the views row plugin "node_rss".
 */
class NodeRss extends Rss {

  /**
   * The base table for this row plugin.
   *
   * @var string
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  public $base_table = 'node';

}
