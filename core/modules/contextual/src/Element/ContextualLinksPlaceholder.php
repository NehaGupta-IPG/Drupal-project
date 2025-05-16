<?php

namespace Drupal\contextual\Element;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a contextual_links_placeholder element.
 */
#[RenderElement('contextual_links_placeholder')]
class ContextualLinksPlaceholder extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        [static::class, 'preRenderPlaceholder'],
      ],
      '#id' => NULL,
    ];
  }

  /**
   * Pre-render callback: Renders a contextual links placeholder into #markup.
   *
   * Renders an empty (hence invisible) placeholder div with a data-attribute
   * that contains an identifier ("contextual id"), which allows the JavaScript
   * of the drupal.contextual-links library to dynamically render contextual
   * links.
   *
   * @param array $element
   *   A structured array with #id containing a "contextual id".
   *
   * @return array
   *   The passed-in element with a contextual link placeholder in '#markup'.
   *
   * @see _contextual_links_to_id()
   */
  public static function preRenderPlaceholder(array $element) {
    $token = Crypt::hmacBase64($element['#id'], Settings::getHashSalt() . \Drupal::service('private_key')->get());
    $attribute = new Attribute([
      'data-contextual-id' => $element['#id'],
      'data-contextual-token' => $token,
      'data-drupal-ajax-container' => '',
    ]);
    $element['#markup'] = new FormattableMarkup('<div@attributes></div>', ['@attributes' => $attribute]);

    return $element;
  }
  public static function createInstance($pre_render, $id, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    if (!$elementInfoManager) {
      $elementInfoManager = \Drupal::service('element_info');
    }
    $instance = $elementInfoManager->createInstance('contextual_links_placeholder');
    $instance->pre_render = $pre_render;
    $instance->id = $id;
    $instance->type = $type;
    $instance->defaults_loaded = $defaults_loaded;
    return $instance;
  }
  
  /**
   * {@inheritdoc}
   */
  public function toRenderArray() {
    return [
      '#pre_render' => $this->pre_render,
      '#id' => $this->id,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
