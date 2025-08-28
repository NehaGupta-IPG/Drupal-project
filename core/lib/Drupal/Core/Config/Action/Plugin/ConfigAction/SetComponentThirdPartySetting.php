<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Action\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets a third-party setting on a display component without overwriting it.
 *
 * Applies to entity view/form display config entities that extend
 * \Drupal\Core\Entity\EntityDisplayBase.
 *
 * Expected value structure for apply():
 * - component (string): The component/field machine name.
 * - provider (string): Module providing the third-party setting.
 * - settings (array|scalar): The setting value or an array of settings.
 *   If an array is provided, it will be merged into the component's
 *   third_party_settings for the given provider.
 *
 * Example usage in a recipe:
 * @code
 * config:
 *   actions:
 *     core.entity_view_display.node.article.default:
 *       setComponentThirdPartySetting:
 *         component: field_image
 *         provider: mymodule
 *         settings:
 *           lazy: true
 * @endcode
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'setComponentThirdPartySetting',
  admin_label: new TranslatableMarkup('Set third-party setting of a display component'),
  entity_types: [
    'entity_view_display',
    'entity_form_display',
  ],
)]
final class SetComponentThirdPartySetting implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly ConfigManagerInterface $configManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get(ConfigManagerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    $entity = $this->configManager->loadConfigEntityByName($configName);
    assert($entity instanceof ConfigEntityInterface);

    if (!is_array($value) || !isset($value['component'], $value['provider'])) {
      throw new ConfigActionException("The setComponentThirdPartySetting action requires an array with 'component' and 'provider'.");
    }

    $component = (string) $value['component'];
    $provider = (string) $value['provider'];
    $settings = $value['settings'] ?? NULL;

    $current = $entity->get('content');
    if (!is_array($current)) {
      $current = [];
    }

    // Ensure the component exists in the display; if hidden, we still allow
    // setting third-party settings by creating/normalizing its structure.
    $component_config = $current[$component] ?? [];
    if (!isset($component_config['settings']) || !is_array($component_config['settings'])) {
      $component_config['settings'] = [];
    }
    if (!isset($component_config['third_party_settings']) || !is_array($component_config['third_party_settings'])) {
      $component_config['third_party_settings'] = [];
    }

    if (is_array($settings)) {
      $existing = $component_config['third_party_settings'][$provider] ?? [];
      if (!is_array($existing)) {
        $existing = [];
      }
      $component_config['third_party_settings'][$provider] = $existing + $settings;
    }
    else {
      // Scalar setting gets stored under a generic key 'value'.
      $component_config['third_party_settings'][$provider]['value'] = $settings;
    }

    $current[$component] = $component_config;
    $entity->set('content', $current);
    $entity->save();
  }

}
