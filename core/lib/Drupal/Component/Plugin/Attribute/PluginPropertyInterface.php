<?php

declare(strict_types=1);

namespace Drupal\Component\Plugin\Attribute;

/**
 * Interface for attributes that provide properties to plugin definitions.
 */
interface PluginPropertyInterface {

  /**
   * Gets the class name of the plugin associated with the attribute.
   *
   * @return class-string
   *   The class name of the plugin associated with the attribute.
   */
  public function getPluginClass(): string;

  /**
   * Sets the class name of the plugin associated with the attribute.
   *
   * @param class-string $class
   *   The class name of the plugin associated with the attribute.
   *
   * @return $this
   */
  public function setPluginClass(string $class): static;

  /**
   * Checks whether the property attribute can be applied to a plugin attribute.
   *
   * @param class-string $pluginAttributeClass
   *   The class of the plugin attribute.
   *
   * @return bool
   *   TRUE if no restriction on allowed plugin classes or plugin class is an
   *   instance of the allowed plugin classes.
   */
  public function isValidPluginAttribute(string $pluginAttributeClass): bool;

  /**
   * Adds the property value to the plugin definition.
   *
   * @param array|object $definition
   *   The plugin attribute data retrieved from Plugin::get().
   *
   * @return array|object
   *   The plugin definition.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function addToDefinition(array|object $definition): array|object;

}
