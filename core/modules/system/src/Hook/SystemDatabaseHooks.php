<?php

namespace Drupal\system\Hook;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Database hook implementations for system.
 */
class SystemDatabaseHooks {

  /**
   * Implements hook_query_TAG_alter() for entity reference selection handlers.
   */
  #[Hook('query_entity_reference_alter')]
  public function queryEntityReferenceAlter(AlterableInterface $query): void {
    $handler = $query->getMetadata('entity_reference_selection_handler');
    $handler->entityQueryAlter($query);
  }

}
