<?php

namespace Drupal\jsonapi\JsonApiResource;

use Drupal\Component\Assertion\Inspector;

/**
 * Represents the data of a relationship object or relationship document.
 *
 * @internal JSON:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 * @see jsonapi.api.php
 */
class RelationshipData extends Data {

  public function __construct(array $data, $cardinality = -1) {
    assert(Inspector::assertAllObjects($data, ResourceIdentifier::class));
    parent::__construct($data, $cardinality);
  }

}
