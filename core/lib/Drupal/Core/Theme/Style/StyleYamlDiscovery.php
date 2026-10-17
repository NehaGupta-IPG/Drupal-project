<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Style;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryTrait;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Core\Theme\Style\Enum\Type;
use Drupal\Core\Theme\Style\Exception\StyleDefinitionErrorException;

/**
 * Discovers styles in YAML files, one file per style type.
 *
 * Styles of type `utility` are discovered in EXTENSION_NAME.utilities.yml
 * files and styles of type `theme` in EXTENSION_NAME.themes.yml files. The
 * type property of each definition is set from the file it is declared in.
 *
 * @internal
 *   This API is experimental.
 */
class StyleYamlDiscovery implements DiscoveryInterface {

  use DiscoveryTrait;

  /**
   * The YAML discoveries, keyed by style type value.
   *
   * @var array<string, \Drupal\Core\Plugin\Discovery\YamlDiscovery>
   */
  protected array $discoveries = [];

  public function __construct(array $directories) {
    foreach (Type::cases() as $type) {
      $this->discoveries[$type->value] = new YamlDiscovery($type->discoveryName(), $directories);
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
   * @throws \Drupal\Core\Theme\Style\Exception\StyleDefinitionErrorException
   *   Thrown when a style ID is declared for more than one type.
   */
  public function getDefinitions() {
    $definitions = [];
    foreach ($this->discoveries as $type => $discovery) {
      foreach ($discovery->getDefinitions() as $id => $definition) {
        if (isset($definitions[$id])) {
          throw new StyleDefinitionErrorException(\sprintf('Style "%s" is declared as %s by %s and as %s by %s.', $id, $definitions[$id]['type'], $definitions[$id]['provider'], $type, $definition['provider']));
        }
        // The file name determines the type.
        $definition['type'] = $type;
        $definitions[$id] = $definition;
      }
    }
    return $definitions;
  }

}
