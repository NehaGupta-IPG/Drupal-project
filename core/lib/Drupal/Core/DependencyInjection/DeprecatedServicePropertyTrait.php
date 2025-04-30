<?php

namespace Drupal\Core\DependencyInjection;

/**
 * Provides a standard way to announce deprecated properties.
 */
trait DeprecatedServicePropertyTrait {

  /**
   * Allows to access deprecated/removed properties.
   *
   * This method must be public.
   */
  public function __get(string $name): mixed {
    if (!isset($this->deprecatedProperties)) {
      throw new \LogicException('The deprecatedProperties property must be defined to use this trait.');
    }

    if (isset($this->deprecatedProperties[$name])) {
      if (is_string($this->deprecatedProperties[$name])) {
        $service_name = $this->deprecatedProperties[$name];
        $class_name = static::class;
        $next_major_version = intval(ceil((float) \Drupal::VERSION)) . '.0.0';
        $message = "The property $name ($service_name service) is deprecated in $class_name and will be removed before Drupal $next_major_version.";
      }
      elseif (
        is_array($this->deprecatedProperties[$name]) &&
        isset($this->deprecatedProperties[$name]['service']) &&
        isset($this->deprecatedProperties[$name]['message'])
      ) {
        $service_name = $this->deprecatedProperties[$name]['service'];
        $message = $this->deprecatedProperties[$name]['message'];
      }
      else {
        throw new \LogicException('The deprecatedProperties property must be a string or an array with service and message keys to use this trait.');
      }

      // phpcs:ignore Drupal.Semantics.FunctionTriggerError
      @trigger_error($message, E_USER_DEPRECATED);
      return \Drupal::service($service_name);
    }

    return NULL;
  }

}
