<?php

declare(strict_types=1);

namespace Drupal\system\Hook;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Block hook implementations for system.
 */
class SystemBlockHooks {

  /**
   * Implements hook_block_view_BASE_BLOCK_ID_alter().
   */
  #[Hook('block_view_system_main_block_alter')]
  public function blockViewSystemMainBlockAlter(array &$build, BlockPluginInterface $block): void {
    // Contextual links on the system_main block would basically duplicate the
    // tabs/local tasks, so reduce the clutter.
    unset($build['#contextual_links']);
  }

}
