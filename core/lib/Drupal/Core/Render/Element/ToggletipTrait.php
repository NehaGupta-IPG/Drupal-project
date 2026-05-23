<?php

namespace Drupal\Core\Render\Element;

/**
 * Provides toggletip processing for render elements.
 */
trait ToggletipTrait {

  /**
   * Process #toggletip property on a render element.
   */
  protected static function processToggletip(array $element): array {
    if (isset($element['#toggletip']['content'])) {
      $toggletip = [
        '#type' => 'toggletip',
        '#content' => $element['#toggletip']['content'],
        '#description' => $element['#toggletip']['atDescription'] ?? '',
      ];

      if (!empty($element['#toggletip']['buttonPlace'])) {
        $toggletip['#button_placement'] = $element['#toggletip']['buttonPlace'];
      }

      if (!empty($element['#toggletip']['place'])) {
        $toggletip['#tip_placement'] = $element['#toggletip']['place'];
      }

      if (!empty($element['#toggletip']['onHover'])) {
        $toggletip['#on_hover'] = TRUE;
      }

      $is_form_element = !empty($element['#input']) && !empty($element['#title']);
      $is_details = isset($element['#type']) && $element['#type'] === 'details';

      if ($is_details) {
        $original_title = $element['#title'];
        $element['#title'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['details-title-wrapper']],
          'title' => ['#markup' => $original_title],
          'toggletip' => $toggletip,
        ];
      }
      elseif ($is_form_element) {
        $element['#field_suffix'] = $toggletip;
      }
      else {
        $element['toggletip_button'] = $toggletip;
      }
    }

    return $element;
  }

}
