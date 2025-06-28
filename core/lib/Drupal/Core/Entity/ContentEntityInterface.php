<?php

namespace Drupal\Core\Entity;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a common interface for all content entity objects.
 *
 * Content entities use fields for all their entity properties and can be
 * translatable and revisionable. Translations and revisions can be
 * enabled per entity type through annotation and using entity type hooks.
 *
 * It's best practice to always implement ContentEntityInterface for
 * content-like entities that should be stored in some database, and
 * enable/disable revisions and translations as desired.
 *
 * When implementing this interface which extends Traversable, make sure to list
 * IteratorAggregate or Iterator before this interface in the implements clause.
 *
 * @see \Drupal\Core\Entity\ContentEntityBase
 * @see \Drupal\Core\Entity\EntityTypeInterface
 *
 * @ingroup entity_api
 */
interface ContentEntityInterface extends \Traversable, FieldableEntityInterface, TranslatableRevisionableInterface, SynchronizableInterface {

  /**
   * Creates an instance of the entity to allow for DI.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param array $values
   *   An array of values to set, keyed by property name. If the entity type
   *   has bundles, the bundle key has to be specified.
   * @param string $entity_type
   *   The type of the entity to create.
   * @param bool $bundle
   *   The bundle of the entity to create.
   * @param array $translations
   *   Any entity translations.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The created entity.
   */
  public static function createInstance(ContainerInterface $container, array $values, string $entity_type, bool $bundle = FALSE, array $translations = []);

}
