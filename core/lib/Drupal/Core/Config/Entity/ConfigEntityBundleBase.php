<?php

namespace Drupal\Core\Config\Entity;

use Drupal\Core\Config\ConfigNameException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * A base class for config entity types that act as bundles.
 *
 * Entity types that want to use this base class must use bundle_of in their
 * annotation to specify for which entity type they are providing bundles for.
 */
abstract class ConfigEntityBundleBase extends ConfigEntityBase {

  /**
   * Deletes display if a bundle is deleted.
   */
  protected function deleteDisplays() {
    // Remove entity displays of the deleted bundle.
    if ($displays = $this->loadDisplays('entity_view_display')) {
      $storage = $this->entityTypeManager()->getStorage('entity_view_display');
      $storage->delete($displays);
    }

    // Remove entity form displays of the deleted bundle.
    if ($displays = $this->loadDisplays('entity_form_display')) {
      $storage = $this->entityTypeManager()->getStorage('entity_form_display');
      $storage->delete($displays);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    $entity_type_manager = $this->entityTypeManager();
    $bundle_of = $this->getEntityType()->getBundleOf();
    if (!$update) {
      \Drupal::service('entity_bundle.listener')->onBundleCreate($this->id(), $bundle_of);
    }
    else {
      // Invalidate the render cache of entities for which this entity
      // is a bundle.
      if ($entity_type_manager->hasHandler($bundle_of, 'view_builder')) {
        $entity_type_manager->getViewBuilder($bundle_of)->resetCache();
      }
      // Entity bundle field definitions may depend on bundle settings.
      \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
      $this->entityTypeBundleInfo()->clearCachedBundles();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    // Prevent the deletion of bundle entities if there are existing content
    // entities of this bundle.
    $entity_type_manager = \Drupal::entityTypeManager();
    foreach ($entities as $entity) {
      if ($bundle_of = $entity->getEntityType()->getBundleOf()) {
        $bundle_of_entity_type = $entity_type_manager->getDefinition($bundle_of);
        $storage = $entity_type_manager->getStorage($bundle_of);

        $has_data = (bool) $storage->getQuery()
          ->condition($bundle_of_entity_type->getKey('bundle'), $entity->id(), '=')
          ->accessCheck(FALSE)
          ->range(0, 1)
          ->execute();

        if ($has_data) {
          // Use the 409 (Conflict) status code to indicate that the deletion
          // could not be completed due to a conflict with the current state of
          // the target resource.
          throw new EntityStorageException("The '{$entity->label()}' bundle contains data and can not be deleted.", 409);
        }
      }
    }

    parent::preDelete($storage, $entities);
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    foreach ($entities as $entity) {
      $entity->deleteDisplays();
      \Drupal::service('entity_bundle.listener')->onBundleDelete($entity->id(), $entity->getEntityType()->getBundleOf());
    }
  }

  /**
   * Acts on an entity before the presave hook is invoked.
   *
   * Used before the entity is saved and before invoking the presave hook.
   *
   * Ensure that config entities which are bundles of other entities cannot have
   * their ID changed.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage object.
   *
   * @throws \Drupal\Core\Config\ConfigNameException
   *   Thrown when attempting to rename a bundle entity.
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Only handle renames, not creations.
    if (!$this->isNew() && $this->getOriginalId() !== $this->id()) {
      $bundle_type = $this->getEntityType();
      $bundle_of = $bundle_type->getBundleOf();
      if (!empty($bundle_of)) {
        throw new ConfigNameException("The machine name of the '{$bundle_type->getLabel()}' bundle cannot be changed.");
      }
    }
  }

  /**
   * Returns view or form displays for this bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID of the display type to load.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface[]
   *   A list of matching displays.
   */
  protected function loadDisplays($entity_type_id) {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage */
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);
    $ids = $storage->getQuery()
      ->condition('id', $this->getEntityType()->getBundleOf() . '.' . $this->getOriginalId() . '.', 'STARTS_WITH')
      ->execute();
    if ($ids) {
      $storage = $this->entityTypeManager()->getStorage($entity_type_id);
      return $storage->loadMultiple($ids);
    }
    return [];
  }

}
