<?php

namespace Drupal\Core\Field\Plugin\Field\FieldType;

/**
 * Interface definition for field items referencing other entities.
 */
interface SavableEntityReferenceItemInterface {

  /**
   * Flags the referenced entity as needing to be saved with the parent entity.
   *
   * @param bool $needsEntitySave
   *   The flag value.
   */
  public function setNeedsEntitySave(bool $needsEntitySave): void;

  /**
   * Determines whether the item holds an unsaved entity.
   *
   * This is notably used for "autocreate" widgets, and more generally to
   * support referencing freshly created entities (they will get saved
   * automatically as the hosting entity gets saved).
   *
   * @return bool
   *   TRUE if the item holds an unsaved entity.
   */
  public function hasNewEntity();

  /**
   * Determines whether the item holds a changed entity.
   *
   * @return bool
   *   TRUE if the item holds a changed entity.
   */
  public function hasChangedEntity(): bool;

}
