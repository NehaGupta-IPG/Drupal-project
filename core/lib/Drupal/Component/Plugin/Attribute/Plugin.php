<?php

namespace Drupal\Component\Plugin\Attribute;

/**
 * Defines a Plugin attribute object.
 *
 * Attributes in plugin classes can use this class in order to pass various
 * metadata about the plugin through the parser to
 * DiscoveryInterface::getDefinitions() calls.
 *
 * @ingroup plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Plugin extends AttributeBase {

  /**
   * Any additional properties and values.
   *
   * @var array
   */
  public array $other = [];

  /**
   * Constructs a plugin attribute object.
   *
   * @param string $id
   *   The plugin ID.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   * @param mixed $other
   *   (optional) Additional properties passed in that can be used by a deriver
   *   or other code.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?string $deriver = NULL,
    ...$other,
  ) {
    $this->other = $other;
  }

  /**
   * {@inheritdoc}
   */
  public function get(): array|object {
    $definition = parent::get();
    unset($definition['other']);
    return $definition + $this->other;
  }

}
