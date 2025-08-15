<?php

namespace Drupal\Core\Htmx;

use Drupal\Component\Utility\Html;

/**
 * Provides helpers to operate on forms.
 *
 * @ingroup form_api
 */
class HtmxHelper {

  /**
   *
   */
  public static function processHtmx(array &$elements) {
    $conf = $elements['#htmx'];
    $elements['#attached']['library'][] = 'core/drupal.htmx';

    $wrapper_key = (($elements['#markup'] ?? FALSE) === '' && ($elements['#input'] ?? FALSE) === TRUE) ? '#wrapper_attributes' : '#attributes';
    if ($elements['#type'] === 'textarea' || $elements['#theme'] === 'item_list') {
      $wrapper_key = '#wrapper_attributes';
    }
    foreach ($conf as $key => $value) {
      switch ($key) {
        case 'post':
        case 'get':
        case 'put':
        case 'delete':
        case 'patch':
          // Only element that triggers a request needs the htmx library.
          $elements['#attributes']["data-hx-$key"] = $value === TRUE ? '' : $value;
          break;

        case 'swap-oob':
          if (empty($elements[$wrapper_key]['id'])) {
            $elements[$wrapper_key]['id'] = Html::getId($elements['#id'] . '-wrapper');
          }
          $elements[$wrapper_key]['data-hx-swap-oob'] = $value === TRUE ? 'true' : $value;
          break;

        case 'swap':
          $elements['#attributes']['data-hx-swap'] = $value;
          break;
      }
    }
    dump($elements);
  }

}
