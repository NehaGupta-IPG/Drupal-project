<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Action\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\Action\Plugin\ConfigAction\Deriver\SimpleConfigArrayDeriver;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'simpleConfigArray',
  deriver: SimpleConfigArrayDeriver::class,
)]
final class SimpleConfigArray implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ConfigManagerInterface $configManager,
    private readonly string $function,
    private readonly string $pluginId,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(ConfigFactoryInterface::class),
      $container->get(ConfigManagerInterface::class),
      $plugin_definition['function'],
      $plugin_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    if ($this->configManager->getEntityTypeIdByName($configName)) {
      throw new ConfigActionException('The ' . $this->pluginId . ' config action cannot be used on configuration entities.');
    }

    $config = $this->configFactory->getEditable($configName);
    if ($config->isNew()) {
      throw new ConfigActionException("Config $configName cannot be updated because it does not exist.");
    }

    assert(is_array($value));
    $values = array_is_list($value) ? $value : [$value];
    foreach ($values as $value) {
      $this->applySingle($config, $value);
    }
    $config->save();
  }

  /**
   * Updates a single array property in a config object.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The config object being updated.
   * @param array $value
   *   An associative array containing:
   *   - `property`: The property path to update in the config object.
   *   - `values`: An array of values to append or prepend to the property, if
   *     this action is appending or prepending.
   *   - ... Named arguments to pass to `array_splice()`, if this action is
   *    splicing into the property's value.
   */
  private function applySingle(Config $config, array $value): void {
    if (empty($value['property'])) {
      throw new ConfigActionException('A property path must be passed to the ' . $this->pluginId . ' config action.');
    }
    $property_name = $value['property'];
    unset($value['property']);

    // The `array_push()` and `array_unshift()` functions have a similar
    // signature, and need to be given an array of values.
    if (in_array($this->function, ['array_push', 'array_unshift'], TRUE)) {
      if (isset($value['values']) && is_array($value['values'])) {
        $arguments = array_values($value['values']);
      }
      else {
        throw new ConfigActionException('The ' . $this->pluginId . ' config action requires an array of values.');
      }
    }
    else {
      // Pass everything in $value directly to the function as named arguments.
      // PHP will validate them on its own.
      $arguments = $value;
    }

    $property_value = $config->get($property_name);
    if (!is_array($property_value)) {
      throw new ConfigActionException("Config " . $config->getName() . " cannot be updated because the property '$property_name' is not an array.");
    }
    ($this->function)($property_value, ...$arguments);
    $config->set($property_name, $property_value);
  }

}
