<?php

namespace Drupal\Core\Block;

use Drupal\Component\Utility\Html;

/**
 * Block theme preprocess.
 *
 * @internal
 */
class BlockPreprocess {

  /**
   * Prepares variables for block templates.
   *
   * Default template: block.html.twig.
   *
   * Prepares the values passed to the theme_block function to be passed
   * into a pluggable template engine. Uses block properties to generate a
   * series of template file suggestions. If none are found, the default
   * block.html.twig is used.
   *
   * Most themes use their own copy of block.html.twig. The default is located
   * inside "core/modules/system/templates/block.html.twig". Look in there for
   * the full list of available variables.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An associative array containing the properties of the
   *     element. Properties used: #block, #configuration, #children,
   *     and #plugin_id.
   */
  public function preprocessBlock(array &$variables): void {
    $variables['configuration'] = $variables['elements']['#configuration'];
    $variables['plugin_id'] = $variables['elements']['#plugin_id'];
    $variables['base_plugin_id'] = $variables['elements']['#base_plugin_id'];
    $variables['derivative_plugin_id'] = $variables['elements']['#derivative_plugin_id'];
    $variables['in_preview'] = $variables['elements']['#in_preview'] ?? FALSE;
    $variables['label'] = !empty($variables['configuration']['label_display']) ? $variables['configuration']['label'] : '';
    $variables['content'] = $variables['elements']['content'];
    // A block's label is configuration: it is static. Allow dynamic labels to
    // be set in the render array.
    if (isset($variables['elements']['content']['#title']) && !empty($variables['configuration']['label_display'])) {
      $variables['label'] = $variables['elements']['content']['#title'];
    }

    // Create a valid HTML ID and make sure it is unique.
    if (!empty($variables['elements']['#id'])) {
      $variables['attributes']['id'] = Html::getUniqueId('block-' . $variables['elements']['#id']);
    }

    // Proactively add aria-describedby if possible to improve accessibility.
    if ($variables['label'] && isset($variables['attributes']['role'])) {
      $variables['title_attributes']['id'] = Html::getUniqueId($variables['label']);
      $variables['attributes']['aria-describedby'] = $variables['title_attributes']['id'];
    }
  }

}
