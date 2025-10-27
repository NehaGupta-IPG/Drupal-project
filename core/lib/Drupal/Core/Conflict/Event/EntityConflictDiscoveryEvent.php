<?php

namespace Drupal\Core\Conflict\Event;

use Drupal\Core\Conflict\ConflictTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Defines an event to discover conflicts.
 */
class EntityConflictDiscoveryEvent extends Event {

  /**
   * An array containing the conflicting properties.
   *
   * The keys are the property names and the values the respective conflict
   * type.
   *
   * @var array<string, \Drupal\Core\Conflict\ConflictTypeInterface>
   */
  protected array $conflicts = [];

  public function __construct(
    protected EntityInterface $localEntity,
    protected EntityInterface $remoteEntity,
    protected EntityInterface $baseEntity,
    protected ParameterBag $context = new ParameterBag(),
  ) {
  }

  /**
   * Return the local entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Local entity.
   */
  public function getLocalEntity() : EntityInterface {
    return $this->localEntity;
  }

  /**
   * Returns the remote entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Remote entity.
   */
  public function getRemoteEntity() : EntityInterface {
    return $this->remoteEntity;
  }

  /**
   * Returns the base entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Base entity.
   */
  public function getBaseEntity() : EntityInterface {
    return $this->baseEntity;
  }

  /**
   * Adds a conflicting property.
   *
   * @param string $property
   *   The property name.
   * @param \Drupal\Core\Conflict\ConflictTypeInterface $type
   *   The conflict type.
   */
  public function addConflict(string $property, ConflictTypeInterface $type): static {
    $this->conflicts[$property] = $type;
    return $this;
  }

  /**
   * Returns the conflicts.
   *
   * @return array<string, \Drupal\Core\Conflict\ConflictTypeInterface>
   *   Array of conflict types keyed by property name.
   */
  public function getConflicts() : array {
    return $this->conflicts;
  }

  /**
   * Returns a parameter value from the context bag.
   *
   * @param string $parameter
   *   The parameter key.
   * @param mixed $default
   *   The default value if the parameter key does not exist.
   *
   * @return mixed
   *   Context value if it exists, otherwise the default.
   */
  public function getContextParameter(string $parameter, mixed $default = NULL): mixed {
    return $this->context->get($parameter, $default);
  }

}
