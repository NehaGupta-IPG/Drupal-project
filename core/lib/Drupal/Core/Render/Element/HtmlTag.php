<?php

namespace Drupal\Core\Render\Element;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html as HtmlUtility;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Markup;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a render element for any HTML tag, with properties and value.
 *
 * Properties:
 * - #tag: The tag name to output.
 * - #attributes: (array, optional) HTML attributes to apply to the tag. The
 *   attributes are escaped, see \Drupal\Core\Template\Attribute.
 * - #value: (string|MarkupInterface, optional) The textual contents of the tag.
 *   Strings will be XSS admin filtered.
 * - #noscript: (bool, optional) When set to TRUE, the markup
 *   (including any prefix or suffix) will be wrapped in a <noscript> element.
 *
 * Usage example:
 * @code
 * $build['hello'] = [
 *   '#type' => 'html_tag',
 *   '#tag' => 'p',
 *   '#value' => $this->t('Hello World'),
 * ];
 * @endcode
 *
 * @see \Drupal\Component\Utility\Xss::filterAdmin().
 */
#[RenderElement('html_tag')]
class HtmlTag extends RenderElementBase {

  /**
   * Void elements do not contain values or closing tags.
   *
   * @var string[]
   *
   * @see https://www.w3.org/TR/html5/syntax.html#syntax-start-tag
   * @see https://www.w3.org/TR/html5/syntax.html#void-elements
   */
  protected static $voidElements = [
    'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
    'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr',
    'rect', 'circle', 'polygon', 'ellipse', 'stop', 'use', 'path',
  ];

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        [static::class, 'preRenderHtmlTag'],
      ],
      '#attributes' => [],
      '#value' => NULL,
    ];
  }

  /**
   * Pre-render callback: Renders a generic HTML tag with attributes.
   *
   * @param array $element
   *   An associative array containing:
   *   - #tag: The tag name to output. Typical tags added to the HTML HEAD:
   *     - meta: To provide meta information, such as a page refresh.
   *     - link: To refer to stylesheets and other contextual information.
   *     - script: To load JavaScript.
   *     The value of #tag is escaped.
   *   - #attributes: (optional) An array of HTML attributes to apply to the
   *     tag. The attributes are escaped, see \Drupal\Core\Template\Attribute.
   *   - #value: (optional) A string containing tag content, such as inline
   *     CSS. The value of #value will be XSS admin filtered if it is not safe.
   *   - #noscript: (optional) If TRUE, the markup (including any prefix or
   *     suffix) will be wrapped in a <noscript> element. (Note that passing
   *     any non-empty value here will add the <noscript> tag.)
   *
   * @return array
   *   The element, after the pre-rendering processing run.
   */
  public static function preRenderHtmlTag($element) {
    $attributes = isset($element['#attributes']) ? new Attribute($element['#attributes']) : '';

    // An HTML tag should not contain any special characters. Escape them to
    // ensure this cannot be abused.
    $escaped_tag = HtmlUtility::escape($element['#tag']);
    $open_tag = '<' . $escaped_tag . $attributes;
    $close_tag = '</' . $escaped_tag . ">\n";
    // Construct a void element.
    if (in_array($element['#tag'], self::$voidElements)) {
      $open_tag .= ' />';
      $close_tag = "\n";
    }
    // Construct all other elements.
    else {
      $open_tag .= '>';
      if ($element['#value'] === NULL) {
        $element['#markup'] = '';
      }
      elseif ($element['#value'] instanceof MarkupInterface) {
        $element['#markup'] = $element['#value'];
      }
      else {
        $element['#markup'] = Markup::create(Xss::filterAdmin($element['#value']));
      }
    }
    $prefix = isset($element['#prefix']) ? $element['#prefix'] . $open_tag : $open_tag;
    $suffix = isset($element['#suffix']) ? $close_tag . $element['#suffix'] : $close_tag;
    if (!empty($element['#noscript'])) {
      $prefix = '<noscript>' . $prefix;
      $suffix .= '</noscript>';
    }
    $element['#prefix'] = Markup::create($prefix);
    $element['#suffix'] = Markup::create($suffix);
    return $element;
  }

  /**
   * Create a html_tag element.
   *
   * @param mixed $pre_render
   *   Automatically converted param.
   * @param mixed $attributes
   *   Automatically converted param.
   * @param mixed $value
   *   Automatically converted param.
   * @param mixed $type
   *   Automatically converted param.
   * @param mixed $defaults_loaded
   *   Automatically converted param.
   * @param ?\Drupal\Core\Render\ElementInfoManager $elementInfoManager
   *   The element info manager.
   *
   * @return static
   */
  public static function createInstance($pre_render, $attributes, $value, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) : static {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('html_tag');
    $instance->pre_render = $pre_render;
    $instance->attributes = $attributes;
    $instance->value = $value;
    $instance->type = $type;
    $instance->defaults_loaded = $defaults_loaded;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() : array {
    return [
      '#pre_render' => $this->pre_render,
      '#attributes' => $this->attributes,
      '#value' => $this->value,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
