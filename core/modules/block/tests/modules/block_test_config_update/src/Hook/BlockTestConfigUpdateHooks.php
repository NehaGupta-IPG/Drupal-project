<?php

declare(strict_types=1);

namespace Drupal\block_test_config_update\Hook;

use Drupal\block_test_config_update\Plugin\Block\TestDisplayMessageConfigUpdate2Block;
use Drupal\block_test_config_update\Plugin\Block\TestDisplayMessageConfigUpdateBlock;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for block_test_config_update.
 */
class BlockTestConfigUpdateHooks {

  /**
   * Implements hook_block_alter().
   */
  #[Hook('block_alter')]
  public function blockAlter(&$block_info): void {
    $block_info['test_block_display_message']['allow_in_navigation'] = TRUE;
    $class = \Drupal::keyValue('block_test')->get('config_update_test_class');
    if ($class) {
      $block_info['test_block_display_message']['class'] = $class;
    }
  }

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(&$definitions): void {
    $class = \Drupal::keyValue('block_test')->get('config_update_test_class');
    switch ($class) {
      case TestDisplayMessageConfigUpdateBlock::class:
        $definitions['block.settings.test_block_display_message']['second_display_message'] = [
          'type' => 'string',
          'label' => 'Second message text',
        ];
        break;

      case TestDisplayMessageConfigUpdate2Block::class:
        $definitions['block.settings.test_block_display_message']['third_display_message'] = [
          'type' => 'string',
          'label' => 'Third message text',
        ];
        break;

      default:
        break;
    }
  }

}
