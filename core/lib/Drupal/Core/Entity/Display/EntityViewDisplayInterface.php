<?php

namespace Drupal\Core\Entity\Display;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Provides a common interface for entity view displays.
 */
interface EntityViewDisplayInterface extends EntityDisplayInterface {

  /**
   * Builds a renderable array for the components of an entity.
   *
   * See the buildMultiple() method for details.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity being displayed.
   *
   * @return array
   *   A renderable array for the entity.
   *
   * @see \Drupal\Core\Entity\Display\EntityViewDisplayInterface::buildMultiple()
   */
  public function build(FieldableEntityInterface $entity);

  /**
   * Builds a renderable array for the components of a set of entities.
   *
   * This only includes the components handled by the Display object, but
   * excludes 'extra fields', that are typically rendered through specific,
   * ad-hoc code in EntityViewBuilderInterface::buildComponents() or in
   * hook_entity_view() implementations.
   *
   * hook_entity_display_build_alter() is invoked on each entity, allowing 3rd
   * party code to alter the render array.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface[] $entities
   *   The entities being displayed.
   *
   * @return array
   *   A renderable array for the entities, indexed by the same keys as the
   *   $entities array parameter.
   *
   * @see hook_entity_display_build_alter()
   */
  public function buildMultiple(array $entities);

  /**
   * True if this entity view display has a page display.
   *
   * Support for this functionality is limited to the 'node' entity-type and the
   * 'full' view mode. Support for other entity-types will be added in
   * https://www.drupal.org/project/drupal/issues/3484255.
   *
   * @return bool
   *   TRUE when this view display is routed.
   */
  public function hasPageDisplay(): bool;

  /**
   * Set has page display.
   *
   * Support for this functionality is limited to the 'node' entity-type and the
   * 'full' view mode. Support for other entity-types will be added in
   * https://www.drupal.org/project/drupal/issues/3484255.
   *
   * @param bool $hasPageDisplay
   *   TRUE to enable a page display for this view mode.
   *
   * @return bool
   *   TRUE when this view display is routed.
   */
  public function setPageDisplay(bool $hasPageDisplay = TRUE): static;

}
