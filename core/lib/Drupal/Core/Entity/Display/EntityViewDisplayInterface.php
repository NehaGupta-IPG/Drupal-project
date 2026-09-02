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
   * phpcs:ignore Drupal.Commenting.FunctionComment.ParamNameNoMatch
   * @param string|null $langcode
   *   (optional) The language the components should be rendered in. Defaults
   *   to NULL, in which case the formatters use the current content language.
   *
   * @return array
   *   A renderable array for the entity.
   *
   * @todo Uncomment the new parameter in Drupal 13.0.0.
   * @see https://www.drupal.org/node/3391714
   * @see \Drupal\Core\Entity\Display\EntityViewDisplayInterface::buildMultiple()
   */
  public function build(FieldableEntityInterface $entity /* , $langcode = NULL */);

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
   * phpcs:ignore Drupal.Commenting.FunctionComment.ParamNameNoMatch
   * @param string|null $langcode
   *   (optional) The language the components should be rendered in. Defaults
   *   to NULL, in which case the formatters use the current content language.
   *
   * @return array
   *   A renderable array for the entities, indexed by the same keys as the
   *   $entities array parameter.
   *
   * @todo Uncomment the new parameter in Drupal 13.0.0.
   * @see https://www.drupal.org/node/3391714
   * @see hook_entity_display_build_alter()
   */
  public function buildMultiple(array $entities /* , $langcode = NULL */);

}
