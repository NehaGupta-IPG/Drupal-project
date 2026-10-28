<?php

namespace Drupal\options_test\Plugin\Options;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\options\Plugin\PredefinedOptionsPluginBase;

/**
 * Plugin implementation for testing purposes.
 *
 * @PredefinedOptions(
 *   id = "predefined_options_test",
 *   label = @Translation("Test"),
 * )
 */
class PredefinedOptionsTestPlugin extends PredefinedOptionsPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getAllowedValues(FieldStorageDefinitionInterface $definition, ?FieldableEntityInterface $entity = NULL, &$cacheable = TRUE) {
    if ($entity === NULL) {
      return [];
    }

    $values = [
      $entity->label(),
      $entity->toUrl()->toString(),
      $entity->uuid(),
      $entity->bundle(),
    ];

    return array_combine($values, $values);
  }

}
