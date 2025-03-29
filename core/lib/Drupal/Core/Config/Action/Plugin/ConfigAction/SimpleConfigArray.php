<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Action\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\Action\Plugin\ConfigAction\Deriver\SimpleConfigArrayDeriver;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'simpleConfigArray',
  admin_label: new TranslatableMarkup('Simple Configuration Array Update'),
  deriver: SimpleConfigArrayDeriver::class
)]
final class SimpleConfigArray implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a SimpleConfigArray object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param array $pluginDefinition
   *   The plugin definition array.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    private readonly array $pluginDefinition,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(ConfigFactoryInterface::class),
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    $config = $this->configFactory->getEditable($configName);

    if ($config->isNew()) {
      throw new ConfigActionException(sprintf('Config %s does not exist so can not be updated', $configName));
    }

    $this->validateValue($value, $this->pluginDefinition['passed_arguments']);

    [$property_name, $passed_options] = self::parseValue($value, $this->pluginDefinition['passed_arguments']);
    $property_value = $config->get($property_name);

    if (!is_array($property_value)) {
      throw new ConfigActionException(sprintf('The property %s must be an array.', $property_name));
    }

    $this->pluginDefinition['function']($property_value, ...$passed_options);

    $config
      ->set($property_name, $property_value)
      ->save();
  }

  /**
   * Validates the value passed to the apply method.
   *
   * @param mixed $value
   *   The value to validate.
   * @param string|array $arguments
   *   The arguments to validate against.
   */
  protected function validateValue(mixed $value, string|array $arguments) {
    if (!is_array($value)) {
      throw new ConfigActionException('Value must be an array.');
    }

    if (!isset($value['property'])) {
      throw new ConfigActionException('The property key is required.');
    }

    if (is_string($arguments) && (!isset($value[$arguments]) || !is_array($value[$arguments]))) {
      throw new ConfigActionException(sprintf('The %s key is required and must be an array.', $arguments));
    }

    if (is_array($arguments)) {
      $missing_keys = array_diff($arguments, array_keys($value));
      if (!empty($missing_keys)) {
        throw new ConfigActionException(sprintf('The following keys are missing: %s.', implode(', ', $missing_keys)));
      }
    }
  }

  /**
   * Parses the value supplied to ::apply().
   *
   * @param array $value
   *   An array with a 'property' key and either and addititional named keys to
   *   pass to the function.
   * @param string|array $arguments
   *   The arguments to validate against.
   *
   * @return array{string, array}
   *   An array where the first element is the property name (string) and the
   *   second element is the values to pass to the function (array).
   */
  private static function parseValue(array $value, string|array $arguments): array {
    $property_name = $value['property'];
    unset($value['property']);

    if (is_string($arguments)) {
      $passed_options = $value[$arguments];
    }
    else {
      $passed_options = array_filter($value, fn($key) => in_array($key, $arguments), ARRAY_FILTER_USE_KEY);
    }

    return [$property_name, $passed_options];
  }

}
