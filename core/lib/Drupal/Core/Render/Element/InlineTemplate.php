<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;

/**
 * Provides a render element where the user supplies an in-line Twig template.
 *
 * Properties:
 * - #template: The inline Twig template used to render the element.
 * - #context: (array) The variables to substitute into the Twig template.
 *   Each variable may be a string or a render array.
 *
 * Usage example:
 * @code
 * $build['hello']  = [
 *   '#type' => 'inline_template',
 *   '#template' => "{% trans %} Hello {% endtrans %} <strong>{{name}}</strong>",
 *   '#context' => [
 *     'name' => $name,
 *   ]
 * ];
 * @endcode
 */
#[RenderElement('inline_template')]
class InlineTemplate extends RenderElementBase {


  /**
   * Automatically converted property.
   *
   * @var mixed
   */
  public $template;

  /**
   * Automatically converted property.
   *
   * @var mixed
   */
  public $context;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        [static::class, 'preRenderInlineTemplate'],
      ],
      '#template' => '',
      '#context' => [],
    ];
  }

  /**
   * Renders a twig string directly.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   */
  // phpcs:ignore Drupal.Commenting.FunctionComment.MissingReturnComment
  public static function preRenderInlineTemplate($element) {
    /** @var \Drupal\Core\Template\TwigEnvironment $environment */
    $environment = \Drupal::service('twig');
    $markup = $environment->renderInline($element['#template'], $element['#context']);
    $element['#markup'] = $markup;
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() : array {
    return [
      '#pre_render' => $this->pre_render,
      '#template' => $this->template,
      '#context' => $this->context,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
