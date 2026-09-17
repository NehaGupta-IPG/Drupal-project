<?php

declare(strict_types=1);

namespace Drupal\Core\TypedData\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\TypedData\Type\BooleanInterface;
use Drupal\Core\TypedData\Type\FloatInterface;
use Drupal\Core\TypedData\Type\IntegerInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\Core\TypedData\TypedData;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\UnionDataDefinition;
use Drupal\Core\TypedData\UnionDataDefinitionInterface;

/**
 * The "union" data type.
 *
 * A union holds a value that may take the shape of any one of the member
 * definitions of its data definition. The member a concrete value takes is
 * decided lazily, the first time the resolution is needed, and is then
 * memoized until the value is replaced with ::setValue().
 *
 * The resolution ladder is, in order:
 * - A NULL value resolves to no member at all. Whether that is allowed is left
 *   to the NotNull constraint of the union definition.
 * - If the definition carries a resolver callback, it is invoked with the raw
 *   value and the union definition. Returning a member definition ends the
 *   resolution; returning NULL falls through to the next rung.
 * - The members are filtered structurally, in declaration order, by the exact
 *   PHP type of the value. No coercion happens here: the string '5' does not
 *   match an integer member. A member whose typed data class maps to none of
 *   the known structural checks always stays a candidate. If exactly one
 *   member survives the filter, the value resolves to it and no validation is
 *   run at all.
 * - Otherwise the surviving candidates are probed in declaration order: a
 *   typed data object is built for each and validated, and the first one
 *   without violations wins and is kept as the resolved delegate. When the
 *   structural filter left no candidate at all, every member is probed.
 * - If nothing validates, the union stays unresolved. The raw value is kept as
 *   it was given, and the UnionMember constraint reports the mismatch.
 *
 * The resolved member is exposed as a real typed data object by
 * ::getResolvedData(). Reading and writing through that object does not
 * re-resolve the union, so a value does not change shape underneath a caller
 * that is holding on to it.
 *
 * @see \Drupal\Core\TypedData\UnionDataDefinition
 * @see \Drupal\Core\Validation\Plugin\Validation\Constraint\UnionMemberConstraint
 *
 * @ingroup typed_data
 */
#[DataType(
  id: "union",
  label: new TranslatableMarkup("Union"),
  definition_class: UnionDataDefinition::class,
  constraints: ["UnionMember" => []],
)]
class Union extends TypedData {

  /**
   * The raw value, as it was passed to ::setValue().
   *
   * @var mixed
   */
  protected $value = NULL;

  /**
   * Whether the raw value has been resolved against the members already.
   */
  protected bool $resolved = FALSE;

  /**
   * The typed data object of the resolved member, if any.
   */
  protected ?TypedDataInterface $resolvedData = NULL;

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $this->resolve();
    // Read back through the delegate, so that changes made to the resolved
    // member are reflected by the union.
    return $this->resolvedData !== NULL ? $this->resolvedData->getValue() : $this->value;
  }

  /**
   * {@inheritdoc}
   *
   * A union never rejects a value. A value that matches no member is kept as
   * it is and reported by the UnionMember constraint during validation.
   */
  public function setValue($value, $notify = TRUE): void {
    $this->value = $value;
    // Replacing the value may change which member the union takes, so the
    // memoized resolution is dropped.
    $this->resolved = FALSE;
    $this->resolvedData = NULL;
    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

  /**
   * Gets the data definition of the member the value resolves to.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface|null
   *   The member definition the value takes, which is the very definition
   *   object listed by the union definition. NULL if the value is NULL or
   *   matches no member.
   */
  public function getResolvedDefinition(): ?DataDefinitionInterface {
    $this->resolve();
    return $this->resolvedData?->getDataDefinition();
  }

  /**
   * Gets the typed data object of the member the value resolves to.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface|null
   *   The typed data object the union delegates to, for example a Map whose
   *   properties can be traversed. NULL if the value is NULL or matches no
   *   member.
   */
  public function getResolvedData(): ?TypedDataInterface {
    $this->resolve();
    return $this->resolvedData;
  }

  /**
   * {@inheritdoc}
   */
  public function getString() {
    $this->resolve();
    if ($this->resolvedData !== NULL) {
      return $this->resolvedData->getString();
    }
    // An unresolved value has no member to ask, so cast what can be cast.
    if ($this->value === NULL) {
      return '';
    }
    if (is_scalar($this->value) || $this->value instanceof \Stringable) {
      return (string) $this->value;
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function setContext($name = NULL, ?TraversableTypedDataInterface $parent = NULL): void {
    parent::setContext($name, $parent);
    // The delegate stands in for the union at the same place in the tree.
    $this->resolvedData?->setContext($name, $parent);
  }

  /**
   * Magic method: Implements a deep clone.
   */
  public function __clone() {
    if ($this->resolvedData !== NULL) {
      $this->resolvedData = clone $this->resolvedData;
      $this->resolvedData->setContext($this->name, $this->parent);
    }
  }

  /**
   * Resolves the raw value against the members, once.
   */
  protected function resolve(): void {
    if ($this->resolved) {
      return;
    }
    // Mark the resolution as done up front, so that a member that reads back
    // from the union while it is being built cannot loop.
    $this->resolved = TRUE;
    $this->resolvedData = NULL;

    if ($this->value === NULL || !$this->definition instanceof UnionDataDefinitionInterface) {
      return;
    }
    $members = $this->definition->getMemberDefinitions();
    if ($members === []) {
      return;
    }

    // A resolver callback decides before anything else is tried.
    $callback = $this->definition->getResolverCallback();
    if ($callback !== NULL && is_callable($callback)) {
      $member = $callback($this->value, $this->definition);
      if ($member instanceof DataDefinitionInterface) {
        $this->resolvedData = $this->createMember($member);
        return;
      }
    }

    // Filter the members structurally, without any coercion.
    $candidates = [];
    foreach ($members as $member) {
      if ($this->matchesStructurally($member)) {
        $candidates[] = $member;
      }
    }
    // A single structural match is taken as is. Validation is deliberately not
    // run: the member is the only shape the value could have, so a value that
    // is invalid for it is invalid, not unresolved.
    if (count($candidates) === 1) {
      $this->resolvedData = $this->createMember($candidates[0]);
      return;
    }

    // Several members match the shape of the value, or none does. Probe them
    // in declaration order and keep the first one that validates cleanly.
    foreach ($candidates ?: $members as $member) {
      try {
        $probe = $this->getTypedDataManager()->create($member, $this->value, '', NULL);
        if (count($probe->validate()) === 0) {
          $probe->setContext($this->name, $this->parent);
          $this->resolvedData = $probe;
          return;
        }
      }
      catch (\Exception) {
        // A member that cannot even hold the value does not match it. For
        // example Map::setValue() throws for a value that is not an array.
        continue;
      }
    }
  }

  /**
   * Builds the delegate typed data object for a member.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $member
   *   The member definition to build the delegate from.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface|null
   *   The delegate, or NULL if the member cannot hold the value.
   */
  protected function createMember(DataDefinitionInterface $member): ?TypedDataInterface {
    try {
      return $this->getTypedDataManager()->create($member, $this->value, $this->name, $this->parent);
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Checks whether the raw value has the shape a member describes.
   *
   * The checks are mapped from the interfaces the typed data class of the
   * member implements, and they test the exact PHP type of the value. A member
   * whose class maps to no check at all, such as 'any', is always a candidate.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $member
   *   The member definition to check the value against.
   *
   * @return bool
   *   TRUE if the value could have the shape of the member.
   */
  protected function matchesStructurally(DataDefinitionInterface $member): bool {
    $class = $member->getClass();
    $value = $this->value;
    $checks = [];
    if (is_a($class, IntegerInterface::class, TRUE)) {
      $checks[] = is_int($value);
    }
    if (is_a($class, FloatInterface::class, TRUE)) {
      $checks[] = is_float($value);
    }
    if (is_a($class, StringInterface::class, TRUE)) {
      $checks[] = is_string($value);
    }
    if (is_a($class, BooleanInterface::class, TRUE)) {
      $checks[] = is_bool($value);
    }
    if (is_a($class, ComplexDataInterface::class, TRUE)) {
      // An empty array is counted as complex data rather than as a list: it is
      // the canonical empty value of a map, and counting it as both would make
      // every union of a map and a list ambiguous for it. A list member can
      // still take an empty array through the probe below, which validates it.
      $checks[] = is_array($value) && ($value === [] || !array_is_list($value));
    }
    if (is_a($class, ListInterface::class, TRUE)) {
      $checks[] = is_array($value) && $value !== [] && array_is_list($value);
    }
    return $checks === [] || in_array(TRUE, $checks, TRUE);
  }

}
