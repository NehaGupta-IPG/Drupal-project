<?php

namespace Drupal\Component\Plugin\Attribute;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;

/**
 * Defines a common interface for classed attributes.
 */
interface AttributeInterface {

  /**
   * Gets the plugin definition.
   *
   * @return array|\Drupal\Component\Plugin\Definition\PluginDefinitionInterface
   *   The plugin definition.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. The
   *   return type will be narrowed to
   *   array|\Drupal\Component\Plugin\Definition\PluginDefinitionInterface.
   *   Implementations returning any other type must be updated.
   */
  public function get(): array|PluginDefinitionInterface;

  /**
   * Gets the name of the provider of the attribute class.
   *
   * @return string|null
   *   The provider of the attribute class.
   */
  public function getProvider(): ?string;

  /**
   * Sets the name of the provider of the attribute class.
   *
   * @param string $provider
   *   The provider of the annotated class.
   */
  public function setProvider(string $provider): void;

  /**
   * Gets the unique ID for this attribute class.
   *
   * @return string
   *   The attribute class ID.
   */
  public function getId(): string;

  /**
   * Gets the class of the attribute class.
   *
   * @return class-string|null
   *   The attribute class.
   */
  public function getClass(): ?string;

  /**
   * Sets the class of the attributed class.
   *
   * @param class-string $class
   *   The class of the attributed class.
   */
  public function setClass(string $class): void;

  /**
   * Gets the dependencies for this attribute class.
   *
   * @return array{"class"?: list<class-string>, "interface"?: list<class-string>, "trait"?: list<class-string>, "provider"?: list<string>}|null
   *   The list of dependencies, keyed by type. If the type is 'class', 'trait',
   *   or 'interface', the values for the type are class names. If the type is
   *   'provider', the values for the type are provider names.
   */
  public function getDependencies(): ?array;

  /**
   * Sets the dependencies for this attribute class.
   *
   * @param array{"class"?: list<class-string>, "interface"?: list<class-string>, "trait"?: list<class-string>, "provider"?: list<string>}|null $dependencies
   *   The list of dependencies, keyed by type. If the type is 'class', 'trait',
   *   or 'interface', the values for the type are class names. If the type is
   *   'provider', the values for the type are provider names.
   */
  public function setDependencies(?array $dependencies): void;

}
