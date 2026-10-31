<?php

declare(strict_types=1);

namespace Drupal\Core\TypedData;

/**
 * Interface for data definitions of unions.
 *
 * A union describes a value that may take the shape of any one of an ordered
 * list of member definitions. Which member a concrete value takes is decided
 * at runtime by \Drupal\Core\TypedData\Plugin\DataType\Union, which resolves
 * the value against the members in declaration order.
 *
 * Only the getters are part of this interface. The matching setters live on
 * \Drupal\Core\TypedData\UnionDataDefinition, following the convention that
 * data definition interfaces describe what can be read, while the concrete
 * definition classes describe how a definition is built.
 *
 * @see \Drupal\Core\TypedData\UnionDataDefinition
 * @see \Drupal\Core\TypedData\Plugin\DataType\Union
 *
 * @ingroup typed_data
 */
interface UnionDataDefinitionInterface extends DataDefinitionInterface {

  /**
   * Gets the definitions of the union members.
   *
   * The order of the returned members is significant: it is the order in which
   * a value is matched against them.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   An ordered, zero-based list of the member data definitions. An empty
   *   array if the union has no members, in which case no value can ever
   *   resolve.
   */
  public function getMemberDefinitions(): array;

  /**
   * Gets the callback deciding which member a value takes.
   *
   * The callback is invoked with the raw value and this definition, and either
   * returns the member definition the value takes, or NULL to let the union
   * fall through to its own resolution.
   *
   * @return string|null
   *   The resolver callback as a string, for example "function_name" or
   *   "ClassName::methodName", or NULL if the union has no resolver callback.
   */
  public function getResolverCallback(): ?string;

}
