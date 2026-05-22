<?php

namespace Drupal\mongodb\Plugin\views\relationship;

use Drupal\mongodb\Driver\Database\mongodb\MongodbSQLException;

/**
 * Overrides the views relationship plugin "entity_reverse".
 */
class EntityReverse extends RelationshipPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // MongoDB does not need reverse relationships.
    throw new MongodbSQLException('MongoDB does not need reverse relationships.');
  }

}
