<?php

declare(strict_types=1);

namespace Drupal\Core\Entity;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface for entities that provide DI instantiation.
 *
 * @ingroup entity_api
 */
interface InjectableEntityInterface extends EntityInterface {

  /**
   * Creates an instance of the entity that allows for DI.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param array $values
   *   An array of values to set, keyed by property name. If the entity type
   *   has bundles, the bundle key has to be specified.
   * @param string $entity_type
   *   The type of the entity to create.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The created entity.
   */
  public static function createInstance(ContainerInterface $container, array $values, string $entity_type);

}
