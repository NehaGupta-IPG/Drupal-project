<?php

/**
 * @file
 * Hooks provided by the Layout Builder module.
 */

/**
 * @defgroup layout_builder_access Layout Builder access
 * @{
 * In determining access rights for the Layout Builder UI,
 * \Drupal\layout_builder\Access\LayoutBuilderAccessCheck checks if the
 * specified section storage plugin (an implementation of
 * \Drupal\layout_builder\SectionStorageInterface) grants access.
 *
 * By default, the Layout Builder access check requires the 'configure any
 * layout' permission. Individual section storage plugins may override this by
 * setting the 'handles_permission_check' attribute key to TRUE. Any section
 * storage plugin that uses 'handles_permission_check' must provide its own
 * complete routing access checking to avoid any access bypasses.
 *
 * This access checking is only enforced on the routing level (not on the entity
 * or field level) with additional form access restrictions. All HTTP API access
 * to Layout Builder data is currently forbidden.
 *
 * @see https://www.drupal.org/project/drupal/issues/2942975
 */

/**
 * @} End of "defgroup layout_builder_access".
 */

/**
 * Alter the regions render array while a section's regions are being built.
 *
 * This hook is invoked during \Drupal\layout_builder\Section::toRenderArray()
 * after the regions array has been initialized and before the layout plugin
 * renders it. Implementations may add, remove, or reorder block render arrays
 * within the regions.
 *
 * The layout_builder module's own implementation (which runs at default
 * priority) populates $regions with each section component's render array.
 * Implementations that need to run before that should use
 * \Drupal\Core\Hook\Order\Order::First; those that need to run after should
 * use \Drupal\Core\Hook\Order\Order::Last.
 *
 * @param array &$regions
 *   The regions render array being built, keyed by region name. Each region
 *   is an array of block render arrays keyed by component UUID.
 * @param \Drupal\layout_builder\Section $section
 *   The section whose regions render array is being built.
 * @param array $context
 *   An associative array with the following keys:
 *   - contexts: An array of \Drupal\Core\Plugin\Context\ContextInterface
 *     objects representing the available contexts.
 *   - in_preview: A boolean indicating whether the section is being previewed
 *     in the Layout Builder UI.
 *
 * @see \Drupal\layout_builder\Section::toRenderArray()
 * @see \Drupal\layout_builder\Hook\LayoutBuilderHooks::layoutBuilderSectionRegionsRenderArrayAlter()
 */
function hook_layout_builder_section_regions_render_array_alter(array &$regions, \Drupal\layout_builder\Section $section, array $context): void {
  // Add a custom render element to a specific region when in preview mode.
  if ($context['in_preview'] && $section->getLayoutId() === 'layout_onecol') {
    $regions['content']['my_custom_element'] = [
      '#markup' => t('Preview-only content'),
    ];
  }
}

/**
 * Alter the render array after a section has been fully built.
 *
 * This hook is invoked during \Drupal\layout_builder\Section::toRenderArray()
 * after the layout plugin has built the final render array from the regions.
 * Implementations may alter the complete section render array, including
 * adding cache metadata or modifying the layout output.
 *
 * @param array &$build
 *   The section's render array as returned by the layout plugin.
 * @param \Drupal\layout_builder\Section $section
 *   The section that was rendered.
 * @param array $context
 *   An associative array with the following keys:
 *   - contexts: An array of \Drupal\Core\Plugin\Context\ContextInterface
 *     objects representing the available contexts.
 *   - in_preview: A boolean indicating whether the section is being previewed
 *     in the Layout Builder UI.
 *
 * @see \Drupal\layout_builder\Section::toRenderArray()
 */
function hook_layout_builder_section_render_array_alter(array &$build, \Drupal\layout_builder\Section $section, array $context): void {
  // Add a wrapper element around the section when not in preview mode.
  if (!$context['in_preview'] && $section->getLayoutId() === 'layout_onecol') {
    $build['my_module_prefix'] = ['#markup' => '<div class="my-module-wrapper">'];
    $build['my_module_suffix'] = ['#markup' => '</div>'];
  }
}
