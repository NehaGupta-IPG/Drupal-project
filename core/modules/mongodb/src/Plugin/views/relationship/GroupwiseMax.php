<?php

namespace Drupal\mongodb\Plugin\views\relationship;

use Drupal\mongodb\Driver\Database\mongodb\MongodbSQLException;

/**
 * Overrides the views relationship plugin "groupwise_max".
 */
class GroupwiseMax extends RelationshipPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // MongoDB does not do groupwise max relationships.
    throw new MongodbSQLException('The MongoDB database driver does not support groupwise max relationships.');
  }

}
