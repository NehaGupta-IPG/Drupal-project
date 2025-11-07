<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Action\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionManager;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\Action\Plugin\ConfigAction\Deriver\SetComponentThirdPartySettingDeriver;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets a third-party setting on an entity form or view display component.
 *
 * The following settings are required:
 * - `component` (string): The machine name of the display component.
 * - `provider` (string): The extension (module or theme) that is providing
 *   the third-party settings.
 * - `settings` (array): An array of third-party settings to store for the given
 *   provider on the given component. Any existing third-party settings will be
 *   replaced.
 *
 * It is possible to use the following placeholders in the settings array:
 * - %bundle: the target bundle of entity display being modified.
 * - %entity_type: the target entity type of entity display being modified.
 * - %view_mode: the display mode of entity display being modified.
 *
 * Example usages in a recipe:
 *
 * @code
 * config:
 *   actions:
 *     # Sets third-party setting of on the `user_picture` component, on
 *     # behalf of the System module.
 *     core.entity_view_display.user.user.default:
 *       setComponentThirdPartySetting:
 *         component: user_picture
 *         provider: system
 *         settings:
 *           example_flag: true
 *
 *     # It is possible to set third-party settings on multiple components.
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
  deriver: SetComponentThirdPartySettingDeriver::class,
)]
final class SetComponentThirdPartySetting implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  use AutowiredInstanceTrait;

  public function __construct(
    private readonly ConfigManagerInterface $configManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return self::createInstanceAutowired($container);
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    assert(is_array($value));

    $entity = $this->configManager->loadConfigEntityByName($configName);
    assert($entity instanceof EntityDisplayInterface);

    // Convert the parameters to a list of parameters.
    $values = array_is_list($value) ? $value : [$value];
    foreach ($values as $value) {
      $this->applySingle($value, $entity);
    }
    $entity->save();
  }

  /**
   * Applies single set of parameters for this config action.
   *
   * @param array $value
   *   The config action parameters.
   * @param \Drupal\Core\Entity\Display\EntityDisplayInterface $entity
   *   The display entity.
   */
  private function applySingle(array $value, EntityDisplayInterface $entity): void {
    if (empty($value['component']) || empty($value['provider'])) {
      throw new ConfigActionException("The setComponentThirdPartySetting action requires an array with 'component' and 'provider'.");
    }
    $provider = $value['provider'];

    // Ensure the component exists in the display.
    $component = $entity->getComponent($value['component']);
    if ($component === NULL) {
      throw new ConfigActionException(sprintf("Component %s does not exist or hidden.", $value['component']));
    }
    $component['third_party_settings'][$provider] = ConfigActionManager::replacePlaceholders($value['settings'] ?? [], [
      '%bundle' => $entity->getTargetBundle(),
      '%entity_type' => $entity->getTargetEntityTypeId(),
      '%display_mode' => $entity->getMode(),
    ]);
    $entity->setComponent($value['component'], $component);
  }

}
