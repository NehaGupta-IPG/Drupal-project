<?php

declare(strict_types=1);

namespace Drupal\Core\Render\Element;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Attribute\RenderElement;

/**
 * Provides a toggletip render element.
 *
 * Properties:
 * - #button_text: (optional) The text content of the button. Defaults to 'i'.
 * - #content: The HTML content to display in the toggletip popover.
 * - #description: (optional) Accessible description for screen readers.
 *   Defaults to 'More info about this'.
 * - #button_placement: (optional) Where the button is positioned relative to
 *   the element (e.g., 'top', 'bottom', 'left', 'right', 'start', 'end',
 *   'center'). Uses CSS anchor positioning API.
 * - #tip_placement: (optional) Where the tip popover appears relative to the
 *   button (e.g., 'top', 'right', 'bottom', 'left', 'right-end', etc.).
 *   Uses Floating UI library for dynamic positioning. If not set, uses
 *   default positioning from tip.js ('top-end').
 * - #on_hover: (optional) Boolean. If TRUE, toggletip opens on hover.
 *
 * Usage:
 * @code
 * $build['toggletip'] = [
 *   '#type' => 'toggletip',
 *   '#button_text' => 'i',
 *   '#content' => '<p>This is the toggletip content.</p>',
 *   '#description' => 'Help about this field',
 *   '#button_placement' => 'end',
 *   '#tip_placement' => 'right',
 * ];
 * @endcode
 *
 * @RenderElement("toggletip")
 */
#[RenderElement('toggletip')]
class Toggletip extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $class = static::class;
    return [
      '#pre_render' => [
        [$class, 'preRenderToggletip'],
      ],
      '#button_text' => 'i',
      '#content' => '',
      '#description' => '',
      '#button_placement' => NULL,
      '#tip_placement' => NULL,
      '#on_hover' => FALSE,
      '#theme_wrappers' => ['toggletip'],
    ];
  }

  /**
   * Pre-render callback: Prepares toggletip variables.
   */
  public static function preRenderToggletip(array $element): array {
    // Filter content for security and store filtered version.
    if (!empty($element['#content'])) {
      $element['#content_filtered'] = Xss::filterAdmin($element['#content']);
      // Convert to render array for template.
      $element['#content'] = [
        '#markup' => $element['#content_filtered'],
      ];
    }

    // Generate unique IDs.
    $base_id = Html::getUniqueId('toggletip');
    $element['#toggle_id'] = $base_id . '-toggle';
    $element['#tip_id'] = $base_id . '-tip';
    $element['#description_id'] = $base_id . '-description';

    // Filter description if provided.
    if (!empty($element['#description'])) {
      $element['#description'] = Xss::filterAdmin($element['#description']);
    }

    // Attach library.
    $element['#attached']['library'][] = 'core/drupal.toggletip';

    return $element;
  }

}
