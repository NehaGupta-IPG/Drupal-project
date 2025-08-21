<?php

declare(strict_types=1);

namespace Drupal\contextual_test\Hook;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for contextual_test.
 */
class ContextualTestHooks {

  /**
   * Implements hook_block_view_alter().
   */
  #[Hook('block_view_alter')]
  public function blockViewAlter(array &$build, BlockPluginInterface $block): void {
    $build['#contextual_links']['contextual_test'] = ['route_parameters' => []];
  }

}
