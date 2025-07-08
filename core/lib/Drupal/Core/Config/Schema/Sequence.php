<?php

namespace Drupal\Core\Config\Schema;

use Drupal\Core\Config\UnsupportedDataTypeConfigException;

/**
 * Defines a configuration element of type Sequence.
 *
 * This object may contain any number and type of nested elements that share
 * a common definition in the 'sequence' property of the configuration schema.
 *
 * Read https://www.drupal.org/node/1905070 for more details about configuration
 * schema, types and type resolution.
 *
 * Note that sequences implement the typed data ComplexDataInterface (via the
 * parent ArrayElement) rather than the ListInterface. This is because sequences
 * may have named keys, which is not supported by ListInterface. From the typed
 * data API perspective sequences are handled as ordered mappings without
 * metadata about existing properties.
 */
class Sequence extends ArrayElement {

  /**
   * {@inheritdoc}
   */
  protected function getElementDefinition($key) {
    $value = $this->value[$key] ?? NULL;
    $definition = [];
    if ($this->definition['sequence']) {
      $definition = $this->definition['sequence'];
    }
    return $this->buildDataDefinition($definition, $value, $key);
  }

  /**
   * {@inheritdoc}
   */
  public function getProperties($include_computed = FALSE) {
    if (!is_array($this->value)) {
      throw new UnsupportedDataTypeConfigException(sprintf("variable type is %s but applied schema class is %s", gettype($this->value), $this->getDataDefinition()->getClass()));
    }

    // Sequence keys should be ordered however the data definition says.
    $data_definition = $this->getDataDefinition();
    assert($data_definition instanceof SequenceDataDefinition);
    $orderby = $data_definition->getOrderBy();

    // Generate a representation of this sequence:
    // 1. defer to each value's own canonical representation
    // (::getElements() respects the stored order in $this->value, not the
    // schema order)
    // 2. respect the `orderby` instruction in the schema
    // @see \Drupal\Core\Config\Schema\SequenceDataDefinition::getOrderBy()
    $representation = $this->getElements();
    match ($orderby) {
      'key' => ksort($representation),
      // `orderby: value` cannot be handled here because the canonical
      // representation of each property is needed.
      // @see \Drupal\Core\Config\TypedConfigManager::getCanonicalRepresentation()
      'value' => '@see \Drupal\Core\Config\TypedConfigManager::getCanonicalRepresentation',
      // Nothing to do when `orderby` is NULL or some other unknown value.
      default => 'no-op',
    };

    return $representation;
  }

}
