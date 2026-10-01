<?php

namespace Drupal\views\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\ViewEntityInterface;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config action for setting display option.
 *
 * The base action accepts any display option in the 'option' key of the
 * payload. The derivatives created by SetDisplayOptionDeriver fix the option
 * (for example 'fields' or 'pager') and, for options that hold a list of
 * handlers, the name of the payload key that identifies the item (for example
 * 'field_name').
 *
 * Each payload is an array with the following keys. Keys marked with (*) are
 * required.
 * - option (*): The display option to change, for example 'fields' or
 *   'pager'. Only read by the generic 'setDisplayOption' action; the derived
 *   actions have it fixed.
 * - settings (*): The value to store. For list options this is the settings of
 *   one handler, for single value options it is the whole value and may be
 *   FALSE or empty.
 * - item / <type>_name: The ID of the handler to add or update, for list
 *   options only. The generic action uses 'item', the derived actions use a
 *   typed key such as 'field_name' or 'filter_name'.
 * - item_sibling and position: Place the item 'before' or 'after' (default)
 *   an existing item instead of at the end of the list.
 * - display_id: The display to change. Defaults to 'default'.
 * - override: Whether to override the default display for this display
 *   instead of changing the default. Defaults to FALSE.
 * - allow_update: Whether an existing item may be replaced. Defaults to TRUE;
 *   when FALSE an existing item causes an error.
 *
 * A list of payloads can be passed to apply several changes with one action;
 * the view is validated and saved once, after all of them were applied.
 *
 * Examples, as used in the 'config.actions' section of a recipe:
 * @code
 * config:
 *   actions:
 *     views.view.content:
 *       # Single value option on the default display.
 *       setPagerInDisplay:
 *         settings:
 *           type: mini
 *           options:
 *             items_per_page: 5
 *       # Add a field before an existing one, for one display only.
 *       setFieldInDisplay:
 *         display_id: page_1
 *         override: true
 *         field_name: changed
 *         item_sibling: title
 *         position: before
 *         settings:
 *           id: changed
 *           table: node_field_data
 *           field: changed
 *           plugin_id: field
 *       # Add several filters at once, a list of payloads.
 *       setFilterInDisplay:
 *         -
 *           filter_name: status
 *           allow_update: false
 *           settings:
 *             id: status
 *             table: node_field_data
 *             field: status
 *             plugin_id: boolean
 *             value: '1'
 *         -
 *           filter_name: type
 *           settings:
 *             id: type
 *             table: node_field_data
 *             field: type
 *             plugin_id: bundle
 *             value:
 *               article: article
 *       # Any other option via the generic action.
 *       setDisplayOption:
 *         option: link_url
 *         settings: /some/path
 * @endcode
 */
#[ConfigAction(
  id: 'view:setDisplayOption',
  admin_label: new TranslatableMarkup('Set option in a Views display'),
  entity_types: ['view'],
  deriver: SetDisplayOptionDeriver::class,
)]
class SetDisplayOption implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager.
   * @param string|null $option
   *   The display option this action is fixed to, or NULL to read it from the
   *   'option' key of the payload.
   * @param string|null $itemKey
   *   The payload key holding the item name for list options, or NULL to read
   *   the item from the 'item' key of the payload.
   */
  public function __construct(
    protected readonly ConfigManagerInterface $configManager,
    protected readonly ?string $option = NULL,
    protected readonly ?string $itemKey = NULL,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $container->get('config.manager'),
      $plugin_definition['option'] ?? NULL,
      $plugin_definition['item_key'] ?? NULL,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    assert(is_array($value));
    if (!array_is_list($value)) {
      $value = [$value];
    }
    // Load the views executable.
    $entity = $this->configManager->loadConfigEntityByName($configName);
    if (!$entity instanceof ViewEntityInterface) {
      throw new ConfigActionException(sprintf('%s is not a view', $configName));
    }
    $view = $entity->getExecutable();
    array_walk($value, $this->applySingle(...), $view);
    $errors = $view->validate();
    if (!empty($errors)) {
      throw new ConfigActionException(sprintf('Validation of the view ended with following errors: %s', implode(', ', array_merge(...array_values($errors)))));
    }
    $view->save();
  }

  /**
   * Configure view display option.
   *
   * @param array $value
   *   A single payload; see the class documentation for the supported keys.
   * @param int $key
   *   The index of the payload in the list of payloads. Unused, but passed by
   *   array_walk().
   * @param \Drupal\views\ViewExecutable $view
   *   The view to change. It is validated and saved by the caller.
   *
   * @throws \Drupal\Core\Config\Action\ConfigActionException
   *   Thrown when the option, settings or item name is missing, when an
   *   existing item may not be updated, or when the sibling does not exist.
   */
  protected function applySingle(array $value, int $key, ViewExecutable $view): void {
    // The display option to change, such as 'fields' or 'pager'. Derived
    // actions have it fixed, the generic action reads it from the payload.
    $option = $this->option ?? $value['option'] ?? NULL;
    if (empty($option)) {
      throw new ConfigActionException('No option provided');
    }
    // Settings of single value options can legitimately be empty or FALSE.
    if (!array_key_exists('settings', $value)) {
      throw new ConfigActionException('No settings provided');
    }
    // The value to store: the settings of one handler for list options, or the
    // complete value for single value options.
    $settings = $value['settings'];
    // The payload key naming the handler to add or update. Only used for list
    // options (fields, filters, ...); single value options have no item.
    $item_key = $this->itemKey ?? 'item';
    $item = FALSE;
    if (!empty($value[$item_key])) {
      $item = $value[$item_key];
    }
    elseif ($this->itemKey !== NULL) {
      throw new ConfigActionException(sprintf('No %s provided', $item_key));
    }
    // The display to change; the default display is used when omitted.
    $display_id = 'default';
    if (!empty($value['display_id'])) {
      $display_id = $value['display_id'];
    }
    // Whether to make the display override the default display's value for
    // this option, instead of changing the default display itself.
    $override = FALSE;
    if (isset($value['override'])) {
      $override = (bool) $value['override'];
    }
    // Whether an item that already exists may be replaced. When FALSE, an
    // existing item results in an exception.
    $allow_update = TRUE;
    if (isset($value['allow_update'])) {
      $allow_update = (bool) $value['allow_update'];
    }
    $view->setDisplay($display_id);
    if ($item) {
      $option_settings = $view->displayHandlers->get($display_id)->getOption($option);
      if (!empty($option_settings[$item]) && !$allow_update) {
        throw new ConfigActionException(sprintf('Item %s already exists in %s display for %s', $item, $display_id, $option));
      }
      // Check if the item needs to be placed in a specific position. This will
      // help in case a field or filter needs to be added not to the end of the
      // list.
      // 'item_sibling' is the ID of the existing item to position relative to.
      if (!empty($value['item_sibling'])) {
        $sibling = $value['item_sibling'];
        // 'position' is 'before' or 'after' the sibling; anything else, or
        // nothing, means 'after'.
        if (!empty($value['position']) && in_array($value['position'], ['before', 'after'])) {
          $position = $value['position'];
        }
        else {
          $position = 'after';
        }
        $key_pos = array_search($sibling, array_keys($option_settings));
        if ($key_pos !== FALSE) {
          if ($position == 'after') {
            $key_pos++;
          }
          if ($key_pos > 0) {
            $second_array = array_splice($option_settings, $key_pos);
            $settings = array_merge($option_settings, [$item => $settings], $second_array);
          }
          else {
            $settings = array_merge([$item => $settings], $option_settings);
          }
        }
        else {
          throw new ConfigActionException(sprintf('Item sibling %s does not exist', $sibling));
        }
      }
      else {
        $option_settings[$item] = $settings;
        $settings = $option_settings;
      }
    }
    if ($override) {
      $view->displayHandlers->get($display_id)->overrideOption($option, $settings);
    }
    else {
      $view->displayHandlers->get($display_id)->setOption($option, $settings);
    }
  }

}
