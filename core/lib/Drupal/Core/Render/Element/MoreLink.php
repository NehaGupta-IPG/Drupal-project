<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a link render element for a "more" link, like those used in blocks.
 *
 * Properties:
 * - #title: The text of the link to generate (defaults to 'More').
 *
 * See \Drupal\Core\Render\Element\Link for additional properties.
 *
 * Usage Example:
 * @code
 * $build['more'] = [
 *   '#type' => 'more_link',
 *   '#url' => Url::fromRoute('examples.more_examples')
 * ]
 * @endcode
 */
#[RenderElement('more_link')]
class MoreLink extends Link {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    return [
      '#title' => $this->t('More'),
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => ['class' => ['more-link']],
        ],
      ],
    ] + $info;
  }

  /**
   * Create a more_link element.
   *
   * @param mixed $title
   *   Automatically converted param.
   * @param mixed $theme_wrappers
   *   Automatically converted param.
   * @param mixed $pre_render
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
  public static function createInstance($title, $theme_wrappers, $pre_render, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) : static {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('more_link');
    $instance->title = $title;
    $instance->theme_wrappers = $theme_wrappers;
    $instance->pre_render = $pre_render;
    $instance->type = $type;
    $instance->defaults_loaded = $defaults_loaded;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() : array {
    return [
      '#title' => $this->title,
      '#theme_wrappers' => $this->theme_wrappers,
      '#pre_render' => $this->pre_render,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
