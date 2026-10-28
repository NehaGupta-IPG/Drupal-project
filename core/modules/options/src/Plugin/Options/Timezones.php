<?php

namespace Drupal\options\Plugin\Options;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\options\Plugin\PredefinedOptionsPluginBase;

/**
 * Plugin implementation that provides a list of timezones options.
 *
 * @PredefinedOptions(
 *   id = "timezones",
 *   label = @Translation("Timezones"),
 * )
 */
class Timezones extends PredefinedOptionsPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getAllowedValues(FieldStorageDefinitionInterface $definition, ?FieldableEntityInterface $entity = NULL, &$cacheable = TRUE) {
    return system_time_zones();
  }

}
