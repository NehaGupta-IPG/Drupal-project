<?php

declare(strict_types=1);

namespace Drupal\Component\Plugin\Attribute;

/**
 * Interface for attributes that set property values on plugin definitions.
 *
 * Plugin definitions are normally described by a single attribute, provided by
 * the module that defines the plugin type. That is a problem when an optional
 * module needs a property on a plugin it does not own. For example, the
 * 'field_ui_base_route' property of entity types is only meaningful to the
 * optional field_ui module, but it can only be declared by the module that
 * provides the entity type.
 *
 * Attributes implementing this interface are additional, repeatable attributes
 * placed on a plugin class alongside the plugin definition attribute. Plugin
 * discovery collects them and applies them to the definition, but only when the
 * module providing the attribute class is installed. When it is not installed,
 * the attribute is ignored and the property is not set.
 *
 * Implementations are instantiated by plugin discovery, so their constructor
 * arguments must be values that can be written in an attribute.
 *
 * @see \Drupal\Component\Plugin\Discovery\AttributeClassDiscovery::addPropertiesToDefinition()
 */
interface PluginPropertyInterface {

  /**
   * Adds the property value to the plugin definition.
   *
   * @param string $id
   *   The plugin ID.
   * @param array|object $definition
   *   The plugin definition, as returned by
   *   \Drupal\Component\Plugin\Attribute\AttributeInterface::get().
   *
   * @return array|object
   *   The plugin definition, with the property value added.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown when the attribute is used on a plugin whose definition it cannot
   *   set a property on.
   */
  public function addToDefinition(string $id, array|object $definition): array|object;

}
