<?php

declare(strict_types=1);

namespace Drupal\Core\Plugin\Discovery;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryTrait;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;

/**
 * Discovers plugins in several kinds of YAML files.
 *
 * Each file name is mapped to a value. This value is set in the definitions
 * discovered in this file, under the configured key. For example, with the
 * names ['utility' => 'utilities', 'theme' => 'themes'] and the key 'type',
 * definitions from EXTENSION_NAME.utilities.yml files get 'type' set to
 * 'utility'.
 *
 * @internal
 *   This API is experimental.
 */
class MultipleYamlDiscovery implements DiscoveryInterface {

  use DiscoveryTrait;

  /**
   * The YAML discoveries, keyed by value.
   *
   * @var array<string, \Drupal\Core\Plugin\Discovery\YamlDiscovery>
   */
  protected array $discoveries = [];

  /**
   * Constructs a MultipleYamlDiscovery object.
   *
   * @param array<string, string> $names
   *   The base filenames to look for in $directories, keyed by the value to
   *   set in the discovered definitions.
   * @param array $directories
   *   An array of directories to scan, keyed by the provider.
   * @param string $key
   *   The definition key to set the value in.
   */
  public function __construct(array $names, array $directories, protected readonly string $key) {
    foreach ($names as $value => $name) {
      $this->discoveries[$value] = new YamlDiscovery($name, $directories);
    }
  }

  /**
   * Set one of the YAML values as being translatable.
   *
   * @param string $value_key
   *   The key corresponding to the value in the YAML that contains a
   *   translatable string.
   * @param string $context_key
   *   (Optional) the translation context for the value specified by the
   *   $value_key.
   *
   * @return $this
   */
  public function addTranslatableProperty(string $value_key, string $context_key = '') {
    foreach ($this->discoveries as $discovery) {
      $discovery->addTranslatableProperty($value_key, $context_key);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown when a plugin ID is declared in more than one kind of file.
   */
  public function getDefinitions() {
    $definitions = [];
    foreach ($this->discoveries as $value => $discovery) {
      foreach ($discovery->getDefinitions() as $id => $definition) {
        if (isset($definitions[$id])) {
          throw new InvalidPluginDefinitionException($id, \sprintf('Plugin "%s" is declared with %s "%s" by %s and with %s "%s" by %s.', $id, $this->key, $definitions[$id][$this->key], $definitions[$id]['provider'], $this->key, $value, $definition['provider']));
        }
        // The file name determines the value.
        $definition[$this->key] = (string) $value;
        $definitions[$id] = $definition;
      }
    }
    return $definitions;
  }

}
