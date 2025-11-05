<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Action\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionManager;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

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
 * - replace (boolean): If TRUE replace settings, otherwise merge.
 * Or alternatively a list of parameters with the same structure as above.
 *
 * It is possible to use such placeholders in component settings:
 * - %bundle - the target bundle of entity display being modified,
 * - %entity_type - the entity type of entity display being modified,
 * - %view_mode - the view/form mode of entity display being modified.
 *
 * Example usages in a recipe:
 *
 * @code
 * config:
 *   actions:
 *     # Sets 3rd party setting of 'system' module on 'user_picture' component.
 *     core.entity_view_display.user.user.default:
 *       setComponentThirdPartySetting:
 *         component: user_picture
 *         provider: system
 *         settings:
 *           example_flag: true
 *     # It is possible to set 3rd party settings of multiple components.
 *     core.entity_view_display.user.user.compact:
 *       setComponentThirdPartySetting:
 *         -
 *           component: user_picture
 *           provider: system
 *           settings:
 *             example_flag: true
 *         -
 *           component: field_first_name
 *           provider: user
 *           settings:
 *             type: text
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
final class SetComponentThirdPartySetting extends PluginBase implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ConfigManagerInterface $configManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    // If config action settings are not an array, do early exit, as action is
    // not possible to execute.
    assert(is_array($value));
    // Convert the parameters to a list of parameters.
    if (!array_is_list($value)) {
      $value = [$value];
    }
    $entity = $this->configManager->loadConfigEntityByName($configName);
    assert($entity instanceof EntityDisplayInterface);
    array_walk($value, [$this, 'applySingle'], $entity);
    $entity->save();
  }

  /**
   * Applies single set of parameters for this config action.
   *
   * @param array $value
   *   The config action parameters.
   * @param int $key
   *   The id of set of parameters.
   * @param \Drupal\Core\Entity\Display\EntityDisplayInterface $entity
   *   The display entity.
   */
  private function applySingle(array $value, int $key, EntityDisplayInterface $entity): void {
    if (empty($value['component']) || empty($value['provider'])) {
      throw new ConfigActionException("The setComponentThirdPartySetting action requires an array with 'component' and 'provider'.");
    }
    $component = $value['component'];
    $provider = $value['provider'];
    $replace = $value['replace'] ?? FALSE;
    // Replace placeholders.
    $value = ConfigActionManager::replacePlaceholders($value, [
      '%bundle' => $entity->getTargetBundle(),
      '%entity_type' => $entity->getTargetEntityTypeId(),
      '%view_mode' => $entity->getMode(),
    ]);

    $settings = $value['settings'] ?? NULL;

    // Ensure the component exists in the display.
    $component_config = $entity->getComponent($component);
    if (empty($component_config)) {
      throw new ConfigActionException(sprintf("Component %s does not exist or hidden.", $component));
    }
    // Make sure the required properties are set.
    $component_config['settings'] ??= [];
    $component_config['third_party_settings'] ??= [];

    if (is_array($settings)) {
      if ($replace) {
        $component_config['third_party_settings'][$provider] = $settings;
      }
      else {
        $existing = $component_config['third_party_settings'][$provider] ?? [];
        if (!is_array($existing)) {
          $existing = [];
        }
        $component_config['third_party_settings'][$provider] = array_merge($existing, $settings);
      }
    }

    $entity->setComponent($component, $component_config);
  }

}
