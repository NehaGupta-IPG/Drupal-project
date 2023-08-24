<?php

namespace Drupal\views_entity_test\Plugin\EntityReferenceSelection;

use Drupal\user\EntityOwnerInterface;
use Drupal\views\Plugin\EntityReferenceSelection\ViewsAutoCreateSelectionBase;

/**
 * Plugin implementation of the 'selection' entity_reference with auto create.
 *
 * @EntityReferenceSelection(
 *   id = "views_auto_create",
 *   label = @Translation("Views: Filter by an entity reference view and allow auto create"),
 *   group = "views_auto_create",
 *   weight = 1
 * )
 */
class TestViewsWithAutoCreateSelection extends ViewsAutoCreateSelectionBase {

  /**
   * {@inheritdoc}
   */
  public function createNewEntity($entity_type_id, $bundle, $label, $uid) {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $bundle_key = $entity_type->getKey('bundle');
    $label_key = $entity_type->getKey('label');

    $entity = $this->entityTypeManager->getStorage($entity_type_id)->create([
      $bundle_key => $bundle,
      $label_key => $label,
    ]);
    if ($entity instanceof EntityOwnerInterface) {
      $entity->setOwnerId($uid);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableNewEntities(array $entities) {
    return array_filter($entities, function ($entity) {
      if (isset($this->configuration['handler_settings']['auto_create_bundle'])) {
        return ($entity->bundle() === $this->configuration['handler_settings']['auto_create_bundle']);
      }
      return TRUE;
    });
  }

}
