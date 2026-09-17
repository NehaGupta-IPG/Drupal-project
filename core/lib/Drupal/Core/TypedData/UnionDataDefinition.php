<?php

declare(strict_types=1);

namespace Drupal\Core\TypedData;

/**
 * A typed data definition class for defining unions.
 *
 * A union is defined by an ordered list of member definitions:
 * @code
 * $definition = UnionDataDefinition::create(
 *   DataDefinition::create('uri'),
 *   MapDataDefinition::create(),
 * );
 * @endcode
 *
 * The members are held in a dedicated property instead of in the definition
 * array, so that they stay definition objects rather than nested arrays, the
 * same way ListDataDefinition holds its item definition.
 *
 * @see \Drupal\Core\TypedData\UnionDataDefinitionInterface
 * @see \Drupal\Core\TypedData\Plugin\DataType\Union
 *
 * @ingroup typed_data
 */
class UnionDataDefinition extends DataDefinition implements UnionDataDefinitionInterface {

  /**
   * The data definitions of the union members, in declaration order.
   *
   * @var \Drupal\Core\TypedData\DataDefinitionInterface[]
   */
  protected array $memberDefinitions = [];

  /**
   * Creates a new union definition.
   *
   * The parameter is an untyped variadic because PHP does not allow a child
   * method to narrow the parameter type of DataDefinition::create($type). The
   * member type is therefore enforced at runtime instead.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface ...$members
   *   (optional) The data definitions of the union members, in the order in
   *   which a value should be matched against them.
   *
   * @return static
   *   A new union data definition object.
   *
   * @throws \InvalidArgumentException
   *   If one of the passed members is not a data definition.
   */
  public static function create(...$members) {
    $definition = new static(['type' => 'union']);
    return $definition->setMemberDefinitions($members);
  }

  /**
   * {@inheritdoc}
   */
  public static function createFromDataType($data_type) {
    if ($data_type !== 'union') {
      throw new \InvalidArgumentException('Union data definitions must be of data type "union".');
    }
    return static::create();
  }

  /**
   * {@inheritdoc}
   */
  public function getDataType() {
    return 'union';
  }

  /**
   * {@inheritdoc}
   */
  public function setDataType($type) {
    if ($type !== 'union') {
      throw new \LogicException('Unions must always be of data type "union".');
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMemberDefinitions(): array {
    return $this->memberDefinitions;
  }

  /**
   * Sets the definitions of the union members.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface[] $members
   *   The member data definitions, in the order in which a value should be
   *   matched against them. The keys are discarded.
   *
   * @return $this
   *
   * @throws \InvalidArgumentException
   *   If one of the passed members is not a data definition.
   */
  public function setMemberDefinitions(array $members) {
    foreach ($members as $member) {
      if (!$member instanceof DataDefinitionInterface) {
        throw new \InvalidArgumentException('Union members must be instances of \Drupal\Core\TypedData\DataDefinitionInterface.');
      }
    }
    $this->memberDefinitions = array_values($members);
    return $this;
  }

  /**
   * Appends a definition to the union members.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $member
   *   The member data definition to append.
   *
   * @return $this
   */
  public function addMemberDefinition(DataDefinitionInterface $member) {
    $this->memberDefinitions[] = $member;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getResolverCallback(): ?string {
    return $this->definition['resolver_callback'] ?? NULL;
  }

  /**
   * Sets the callback deciding which member a value takes.
   *
   * Only string callables are accepted, so that the definition stays
   * serializable. This mirrors
   * \Drupal\Core\Field\BaseFieldDefinition::setDefaultValueCallback().
   *
   * @param string|null $callback
   *   The callback to invoke for resolving a value, as a string like
   *   "function_name" or "ClassName::methodName", or NULL to remove it. The
   *   callback is invoked with the raw value and this definition, and returns
   *   either the member definition the value takes or NULL to let the union
   *   resolve the value itself.
   *
   * @return $this
   *
   * @throws \InvalidArgumentException
   *   If the callback is neither a string nor NULL.
   */
  public function setResolverCallback($callback) {
    if (isset($callback) && !is_string($callback)) {
      throw new \InvalidArgumentException('Union resolver callback must be a string, like "function_name" or "ClassName::methodName"');
    }
    $this->definition['resolver_callback'] = $callback;
    return $this;
  }

  /**
   * Magic method: Implements a deep clone.
   */
  public function __clone() {
    // Ensure the member definitions are actually cloned by overwriting the
    // original references. A union that is cloned in order to be changed must
    // not change the members of the definition it was cloned from.
    foreach ($this->memberDefinitions as $delta => $member) {
      $this->memberDefinitions[$delta] = clone $member;
    }
  }

}
