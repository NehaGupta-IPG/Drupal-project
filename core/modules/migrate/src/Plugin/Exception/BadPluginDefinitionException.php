<?php

namespace Drupal\migrate\Plugin\Exception;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;

/**
 * Defines a class for bad plugin definition exceptions.
 */
class BadPluginDefinitionException extends InvalidPluginDefinitionException {

  public function __construct($plugin_id, $property, $code = 0, ?\Throwable $previous = NULL) {
    $message = sprintf('The %s plugin must define the %s property.', $plugin_id, $property);
    parent::__construct($plugin_id, $message, $code, $previous);
  }

}
