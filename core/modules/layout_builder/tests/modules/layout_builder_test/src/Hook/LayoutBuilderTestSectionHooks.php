<?php

declare(strict_types=1);

namespace Drupal\layout_builder_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\layout_builder\Section;

/**
 * Section render array hook implementations for layout_builder_test.
 */
class LayoutBuilderTestSectionHooks {

  /**
   * Whether the hooks are active.
   */
  protected bool $active = FALSE;

  /**
   * Activates or deactivates the hooks.
   *
   * @param bool $active
   *   Whether to activate the hooks.
   */
  public function setActive(bool $active): void {
    $this->active = $active;
  }

  /**
   * Executes before layout_builder builds the regions.
   *
   * Implements hook_layout_builder_section_regions_render_array_alter().
   */
  #[Hook('layout_builder_section_regions_render_array_alter', order: Order::First)]
  public function beforeBuildRegionsRenderArray(array &$regions, Section $section, array $context): void {
    if (!$this->active) {
      return;
    }
    $regions['main']['before'] = [
      '#markup' => '3rd party: before',
    ];
  }

  /**
   * Executes after layout_builder has built the regions.
   *
   * Implements hook_layout_builder_section_regions_render_array_alter().
   */
  #[Hook('layout_builder_section_regions_render_array_alter', order: Order::Last)]
  public function afterBuildRegionsRenderArray(array &$regions, Section $section, array $context): void {
    if (!$this->active) {
      return;
    }
    $regions['main']['after'] = [
      '#markup' => '3rd party: after',
    ];
    // Alter also content provided by the core hook.
    $regions['main']['first-uuid']['content']['#markup'] = '3rd party: replaced';
  }

  /**
   * Executes after layout_builder has built the section render array.
   *
   * Implements hook_layout_builder_section_render_array_alter().
   */
  #[Hook('layout_builder_section_render_array_alter')]
  public function afterBuildSectionRenderArray(array &$build, Section $section, array $context): void {
    if (!$this->active) {
      return;
    }
    $build['third_party'] = [
      '#markup' => '3rd party: section',
    ];
  }

}
