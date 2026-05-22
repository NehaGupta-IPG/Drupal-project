<?php

namespace Drupal\mongodb\Plugin\views\row;

use Drupal\comment\Plugin\views\row\Rss;

/**
 * Overrides the views row plugin "comment_rss".
 */
class CommentRss extends Rss {

  /**
   * The base table for this row plugin.
   *
   * @var string
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  public $base_table = 'comment';

}
