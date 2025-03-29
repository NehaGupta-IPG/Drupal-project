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

    // @todo https://www.drupal.org/i/3439713 Should we error if this is a
    //   config entity?
    if ($config->isNew()) {
      throw new ConfigActionException(sprintf('Config %s does not exist so can not be updated', $configName));
    }

    if (!isset($value['property'])) {
      throw new ConfigActionException('The property key is required.');
    }

    [$property_name, $passed_options] = self::parseValue($value);

    $property_value = $config->get($property_name);
    $this->pluginDefinition['function']($property_value, ...$passed_options);

    $config
      ->set($property_name, $property_value)
      ->save();
  }

  /**
   * Parses the value supplied to ::apply().
   *
   * @param array $value
   *   An array with a 'property' key and either and addititional named keys to
   *   pass to the function.
   *
   * @return array{string, array}
   *   An array where the first element is the property name (string) and the
   *   second element is the values to pass to the function (array).
   */
  private static function parseValue(array $value): array {
    $property = $value['property'];
    unset($value['property']);

    return [$property, $value['values'] ?? $value];
  }

}
