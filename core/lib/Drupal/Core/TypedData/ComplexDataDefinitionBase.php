<?php

namespace Drupal\Core\TypedData;

/**
 * Base class for complex data definitions.
 */
abstract class ComplexDataDefinitionBase extends DataDefinition implements ComplexDataDefinitionInterface {

  /**
   * An array of data definitions.
   *
   * @var \Drupal\Core\TypedData\DataDefinitionInterface[]
   */
  protected $propertyDefinitions;

  /**
   * {@inheritdoc}
   */
  abstract public function getPropertyDefinitions();

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinition($name) {
    $definitions = $this->getPropertyDefinitions();
    if (isset($definitions[$name])) {
      return $definitions[$name];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMainPropertyName() {
    return NULL;
  }

}
