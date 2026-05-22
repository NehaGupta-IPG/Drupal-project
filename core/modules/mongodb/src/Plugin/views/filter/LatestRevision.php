<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\LatestRevision as CoreLatestRevision;

/**
 * Overriding the views filter plugin "latest_revision".
 */
class LatestRevision extends CoreLatestRevision {

  use FilterPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing.
  }

}
