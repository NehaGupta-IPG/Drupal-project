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
      throw new ConfigActionException(sprintf('Config %s does not exist so can not be updated.', $configName));
    }

    if (!is_array($value)) {
      throw new ConfigActionException(sprintf('Config %s can not be updated because $value is not an array.', $configName));
    }

    if (!isset($value['property'])) {
      throw new ConfigActionException(sprintf('Config %s can not be updated because the property argument was not passed.', $configName));
    }

    $required_arguments = $this->pluginDefinition['required_arguments'];
    if (is_string($required_arguments) && (!isset($value[$required_arguments]) || !is_array($value[$required_arguments]))) {
      throw new ConfigActionException(sprintf('Config %s can not be updated because the %s argument is required and must be an array.', $configName, $required_arguments));
    }

    if (is_array($required_arguments)) {
      $missing_arguments = array_diff($required_arguments, array_keys($value));
      if (!empty($missing_arguments)) {
        throw new ConfigActionException(sprintf('Config %s can not be updated because the following arguments are missing: %s.', $configName, implode(', ', $missing_arguments)));
      }
    }

    [$property_name, $passed_options] = self::parseValue($value, $required_arguments);
    $property_value = $config->get($property_name);

    if (!is_array($property_value)) {
      throw new ConfigActionException(sprintf('Config %s can not be updated because the property %s is not an array.', $configName, $property_name));
    }

    $this->pluginDefinition['function']($property_value, ...$passed_options);

    $config
      ->set($property_name, $property_value)
      ->save();
  }

  /**
   * Parses the value supplied to ::apply().
   *
   * @param array $value
   *   An array with a 'property' key and either and additional named keys to
   *   pass to the function.
   * @param string|array $required_arguments
   *   The arguments to validate against.
   *
   * @return array{string, array}
   *   An array where the first element is the property name (string) and the
   *   second element is the values to pass to the function (array).
   */
  private static function parseValue(array $value, string|array $required_arguments): array {
    $property_name = $value['property'];
    unset($value['property']);

    if (is_string($required_arguments)) {
      $passed_options = $value[$required_arguments];
    }
    else {
      $passed_options = array_filter(
        $value,
        fn ($key) => in_array($key, $required_arguments),
        ARRAY_FILTER_USE_KEY
      );
    }

    return [$property_name, $passed_options];
  }

}
