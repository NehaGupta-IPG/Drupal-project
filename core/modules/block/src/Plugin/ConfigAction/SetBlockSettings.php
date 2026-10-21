<?php

declare(strict_types=1);

namespace Drupal\block\Plugin\ConfigAction;

use Drupal\block\BlockInterface;
use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'block:setBlockSettings',
  admin_label: new TranslatableMarkup('Set Block Settings'),
  entity_types: ['block'],
)]
final class SetBlockSettings implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a SetBlockSettings object.
   *
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager.
   */
  public function __construct(
    protected readonly ConfigManagerInterface $configManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($container->get('config.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    $block = $this->configManager->loadConfigEntityByName($configName);
    if (!$block instanceof BlockInterface) {
      throw new ConfigActionException(sprintf('The config %s is not a valid Drupal block.', $configName));
    }

    // Expect $value to be an array whose keys are the block settings keys
    // to update.
    if (!is_array($value)) {
      throw new ConfigActionException(sprintf('Config %s can not be updated because $value is not an array.', $configName));
    }

    // Get the existing settings.
    $block_settings = $block->get('settings');

    // Check for invalid settings.
    if ($invalid = array_diff(array_keys($value), array_keys($block_settings))) {
      throw new ConfigActionException(sprintf('Invalid settings "%s" provided for the block %s.', implode(',', $invalid), $configName));
    }

    // Update the block settings and save the block.
    foreach ($value as $key => $val) {
      $block_settings[$key] = $val;
    }
    $block->set('settings', $block_settings)->save();
  }

}
