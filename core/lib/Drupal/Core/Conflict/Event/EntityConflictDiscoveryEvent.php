<?php

declare(strict_types=1);

namespace Drupal\Core\Conflict\Event;

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
   * @var array<string, \UnitEnum>
   */
  protected array $conflicts = [];

  public function __construct(
    protected EntityInterface $oursEntity,
    protected EntityInterface $theirsEntity,
    protected EntityInterface $baseEntity,
    protected ParameterBag $context = new ParameterBag(),
  ) {
    \assert($oursEntity->getEntityTypeId() === $theirsEntity->getEntityTypeId() && $theirsEntity->getEntityTypeId() === $baseEntity->getEntityTypeId());
    \assert($oursEntity->id() === $theirsEntity->id() && $theirsEntity->id() === $baseEntity->id());
  }

  /**
   * Return the ours entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Ours entity.
   */
  public function getOursEntity() : EntityInterface {
    return $this->oursEntity;
  }

  /**
   * Returns the theirs entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Theirs entity.
   */
  public function getTheirsEntity() : EntityInterface {
    return $this->theirsEntity;
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
   * @param \UnitEnum $type
   *   The conflict type.
   */
  public function addConflict(string $property, \UnitEnum $type): static {
    $this->conflicts[$property] = $type;
    return $this;
  }

  /**
   * Returns the conflicts.
   *
   * @return array<string, \UnitEnum>
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
   *   (optional) The default value if the parameter key does not exist.
   *
   * @return mixed
   *   Context value if it exists, otherwise the default.
   */
  public function getContextParameter(string $parameter, mixed $default = NULL): mixed {
    return $this->context->get($parameter, $default);
  }

}
