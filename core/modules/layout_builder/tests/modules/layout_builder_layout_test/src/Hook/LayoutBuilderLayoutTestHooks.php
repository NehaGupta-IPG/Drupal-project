<?php

declare(strict_types=1);

namespace Drupal\layout_builder_layout_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hooks for layout_builder_layout_test.
 */
class LayoutBuilderLayoutTestHooks {

  /**
   * Implements hook_layout_alter().
   */
  #[Hook('layout_alter')]
  public function layoutAlter(&$definitions): void {
    // Remove the layout conditionally.
    /** @var \Drupal\Core\Layout\LayoutDefinition[] $definitions */
    if (\Drupal::state()->get('layout_builder_layout_test.remove_layout')) {
      unset($definitions['layout_builder_test_layout']);
    }
  }

}
