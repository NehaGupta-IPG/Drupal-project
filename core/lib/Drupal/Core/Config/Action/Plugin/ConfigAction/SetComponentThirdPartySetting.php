<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Action\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
 * Or alternatively a list of parameters with the same structure as above.
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
final class SetComponentThirdPartySetting implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The placeholder which is replaced with the ID of the current bundle.
   *
   * @var string
   */
  private const BUNDLE_PLACEHOLDER = '%bundle';

  /**
   * The placeholder which is replaced with the ID of the current entity type.
   *
   * @var string
   */
  private const ENTITY_TYPE_PLACEHOLDER = '%entity_type';

  /**
   * The placeholder which is replaced with the ID of the current view mode.
   *
   * @var string
   */
  private const VIEW_MODE_PLACEHOLDER = '%view_mode';

  /**
   * Constructs a SetComponentThirdPartySetting object.
   *
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    private readonly ConfigManagerInterface $configManager,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get(ConfigManagerInterface::class),
      $container->get(ModuleHandlerInterface::class),
    );
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
    else {
      // Check that each config action parameter is an array.
      foreach ($value as $item) {
        if (!is_array($item)) {
          throw new ConfigActionException("The setComponentThirdPartySetting action requires an array of settings.");
        }
      }
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
    if (!isset($value['component'], $value['provider'])) {
      throw new ConfigActionException("The setComponentThirdPartySetting action requires an array with 'component' and 'provider'.");
    }

    if (!is_string($value['component']) || $value['component'] === '') {
      throw new ConfigActionException("The 'component' must be a non-empty string.");
    }
    if (!is_string($value['provider']) || $value['provider'] === '') {
      throw new ConfigActionException("The 'provider' must be a non-empty string.");
    }
    $component = $value['component'];
    $provider = $value['provider'];
    if (!$this->moduleHandler->moduleExists($provider)) {
      throw new ConfigActionException("The 'provider' module '$provider' does not exist or is not enabled.");
    }
    // Replace placeholders.
    $value = static::replacePlaceholders($value, [
      static::BUNDLE_PLACEHOLDER => $entity->getTargetBundle(),
      static::ENTITY_TYPE_PLACEHOLDER => $entity->getTargetEntityTypeId(),
      static::VIEW_MODE_PLACEHOLDER => $entity->getMode(),
    ]);

    $settings = $value['settings'] ?? NULL;

    // Ensure the component exists in the display; if hidden, we still allow
    // setting third-party settings by creating/normalizing its structure.
    $component_config = $entity->getComponent($component) ?? [];
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
      $component_config['third_party_settings'][$provider] = array_merge($existing, $settings);
    }
    else {
      // Scalar setting gets stored under a generic key 'value'.
      $component_config['third_party_settings'][$provider]['value'] = $settings;
    }

    $entity->setComponent($component, $component_config);
  }

  /**
   * Replaces placeholders recursively.
   *
   * @param mixed $data
   *   The data to process. If this is an array, it'll be processed recursively.
   * @param array $replacements
   *   An array whose keys are the placeholders to replace in the data, and
   *   whose values are the the replacements. Normally this will only mention
   *   the `%bundle` and `%label` placeholders. If $data is an array, the only
   *   placeholder that is replaced in the array's keys is `%bundle`.
   *
   * @return mixed
   *   The given $data, with the `%bundle` and `%label` placeholders replaced.
   */
  private static function replacePlaceholders(mixed $data, array $replacements): mixed {
    assert(array_key_exists(static::BUNDLE_PLACEHOLDER, $replacements));

    if (is_string($data)) {
      $data = str_replace(array_keys($replacements), $replacements, $data);
    }
    elseif (is_array($data)) {
      foreach ($data as $old_key => $value) {
        $value = static::replacePlaceholders($value, $replacements);

        // Only replace the `%bundle` placeholder in array keys.
        $new_key = str_replace(static::BUNDLE_PLACEHOLDER, $replacements[static::BUNDLE_PLACEHOLDER], $old_key);
        if ($old_key !== $new_key) {
          unset($data[$old_key]);
        }
        $data[$new_key] = $value;
      }
    }
    return $data;
  }

}
