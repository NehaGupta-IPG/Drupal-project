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
 */
#[ConfigAction(
  id: 'view:setDisplayOption',
  admin_label: new TranslatableMarkup('Views: Set Display Option'),
  entity_types: ['view'],
)]
class SetDisplayOption implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs instance of ViewsDisplayOptionBase.
   *
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The configuration manager.
   */
  public function __construct(
    protected readonly ConfigManagerInterface $configManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($container->get('config.manager'));
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
    if (empty($entity)) {
      throw new ConfigActionException(sprintf('View %s does not exist', $configName));
    }
    if (!$entity instanceof ViewEntityInterface) {
      throw new ConfigActionException(sprintf('%s is not view', $configName));
    }
    $view = $entity->getExecutable();
    array_walk($value, [$this, 'applySingle'], $view);
    $errors = $view->validate();
    if (!empty($errors)) {
      throw new ConfigActionException(sprintf('Validation of the view ended with following errors: %s', implode(', ', $errors)));
    }
    $view->save();
  }

  /**
   * Configure view display option.
   *
   * {@inheritdoc}
   */
  protected function applySingle(array $value, int $key, ViewExecutable $view): void {
    if (empty($value)) {
      throw new ConfigActionException(sprintf('View %s cannot be updated because no option settings were provided', $view->id()));
    }
    if (empty($value['option'])) {
      throw new ConfigActionException('No option provided');
    }
    $option = $value['option'];
    if (empty($value['settings'])) {
      throw new ConfigActionException('No settings provided');
    }
    $settings = $value['settings'];
    $item = FALSE;
    if (!empty($value['item'])) {
      $item = $value['item'];
    }
    $display_id = 'default';
    if (!empty($value['display_id'])) {
      $display_id = $value['display_id'];
    }
    $override = FALSE;
    if (isset($value['override'])) {
      $override = (bool) $value['override'];
    }
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
      if (!empty($value['item_sibling'])) {
        $sibling = $value['item_sibling'];
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
