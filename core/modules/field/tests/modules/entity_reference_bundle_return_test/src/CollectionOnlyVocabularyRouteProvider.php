<?php

declare(strict_types=1);

namespace Drupal\entity_reference_bundle_return_test;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\taxonomy\Entity\Routing\VocabularyRouteProvider;

/**
 * Keeps the add route available without an add-form link template.
 */
class CollectionOnlyVocabularyRouteProvider extends VocabularyRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type) {
    $definition = clone $entity_type;
    $definition->setLinkTemplate('add-form', '/admin/structure/taxonomy/add');
    return parent::getAddFormRoute($definition);
  }

}
