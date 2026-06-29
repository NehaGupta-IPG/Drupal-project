<?php

declare(strict_types=1);

namespace Drupal\Core\Conflict\ConflictResolver;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Provides an interface for conflict resolver managers.
 */
interface ConflictResolverManagerInterface {

  /**
   * Resolves the conflicts between two entities based on their common parent.
   *
   * @param \Drupal\Core\Entity\EntityInterface $ours
   *   The ours part of the comparison - for example the entity built of the
   *   user input on an entity form submission. This is basically the active
   *   entity object.
   * @param \Drupal\Core\Entity\EntityInterface $theirs
   *   The theirs part of the comparison - for example the current version of
   *   the entity from the storage or from another branch.
   * @param \Drupal\Core\Entity\EntityInterface $base
   *   The initial entity version in concurrent editing or the lowest common
   *   ancestor in a revision tree scenario.
   * @param ?\Drupal\Core\Entity\EntityInterface $result
   *   (optional) The result entity, on which to apply the result. Usually this
   *   will be the active entity object - the ours entity. If none given, then
   *   the conflict resolutions will be applied on the ours entity.
   * @param \Symfony\Component\HttpFoundation\ParameterBag $context
   *   (optional) The context parameter bag.
   * @param null|array<string, \UnitEnum> $conflicts
   *   (optional) The conflicts as returned by ::getConflicts() or a sub-set of
   *   them in order to limit the conflict resolution only to certain conflicts.
   *   If a null value is passed then a conflict detection will be performed.
   *   Passing an empty array signals will not discover any conflicts or perform
   *   any resolution.
   *
   * @return array<string, \UnitEnum>
   *   An associative array keyed by the conflicting top-level properties,
   *   having as values the corresponding conflict type.
   */
  public function resolveConflicts(
    EntityInterface $ours,
    EntityInterface $theirs,
    EntityInterface $base,
    ?EntityInterface $result = NULL,
    ParameterBag $context = new ParameterBag(),
    ?array $conflicts = NULL,
  ) : array;

  /**
   * Returns the conflicts between two entities based on their common parent.
   *
   * @param \Drupal\Core\Entity\EntityInterface $ours
   *   The ours part of the comparison - for example the entity built of the
   *   user input on an entity form submission. This is basically the active
   *   entity object.
   * @param \Drupal\Core\Entity\EntityInterface $theirs
   *   The theirs part of the comparison - for example the current version of
   *   the entity from the storage.
   * @param \Drupal\Core\Entity\EntityInterface $base
   *   The initial entity version in concurrent editing or the lowest common
   *   ancestor in a revision tree scenario.
   * @param \Symfony\Component\HttpFoundation\ParameterBag $context
   *   (optional) The context parameter bag.
   *
   * @return array<string, \UnitEnum>
   *   An associative array keyed by the conflicting top-level properties,
   *   having as values the corresponding conflict type.
   */
  public function getConflicts(
    EntityInterface $ours,
    EntityInterface $theirs,
    EntityInterface $base,
    ParameterBag $context = new ParameterBag(),
  ) : array;

}
