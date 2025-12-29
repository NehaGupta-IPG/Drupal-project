<?php

namespace Drupal\Core\Entity;

/**
 * Provides methods for an entity to support revision UUIDs.
 */
interface RevisionUuidInterface {

  /**
   * Gets the revision universally-unique identifier of the entity revision.
   *
   * This identifier can be used when moving specific revisions between two
   * systems. The UUID of the entity itself is not unique enough for the
   * revision and the revision ID has no meaning.
   *
   * Note, all translations share the same revision UUID.
   *
   * @return string|null
   *   The revision UUID of the entity, or NULL if the entity does not
   *   support revision UUID's.
   */
  public function getRevisionUuid(): ?string;

}
