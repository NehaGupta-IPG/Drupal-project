<?php

declare(strict_types=1);

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a render element to display an icon.
 *
 * Properties:
 * - #pack_id: (string) Icon Pack provider plugin id.
 * - #icon_id: (string) Name of the icon.
 * - #settings: (array) Settings sent to the inline Twig template.
 *
 * Usage Example:
 * @code
 * $build['icon'] = [
 *   '#type' => 'icon',
 *   '#pack_id' => 'material_symbols',
 *   '#icon_id' => 'home',
 *   '#settings' => [
 *     'width' => 64,
 *   ],
 * ];
 * @endcode
 *
 * @internal
 */
#[RenderElement('icon')]
class Icon extends RenderElementBase {


  /**
   * @var mixed Automatically converted property
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  public $pack_id;

  /**
   * @var mixed Automatically converted property
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  public $icon_id;

  /**
   * @var mixed Automatically converted property
   */
  public $settings;

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#pre_render' => [
        [self::class, 'preRenderIcon'],
      ],
      '#pack_id' => '',
      '#icon_id' => '',
      '#settings' => [],
    ];
  }

  /**
   * Icon element pre render callback.
   *
   * @param array $element
   *   An associative array containing the properties of the icon element.
   *
   * @return array
   *   The modified element.
   */
  public static function preRenderIcon(array $element): array {
    $icon_full_id = IconDefinition::createIconId($element['#pack_id'], $element['#icon_id']);

    $pluginManagerIconPack = \Drupal::service('plugin.manager.icon_pack');
    if (!$icon = $pluginManagerIconPack->getIcon($icon_full_id)) {
      return $element;
    }

    // Build context minimal values as icon_id, optional source and attributes.
    $context = [
      'icon_id' => $icon->getIconId(),
    ];
    // Better to not have source value if not set for the template.
    if ($source = $icon->getSource()) {
      $context['source'] = $source;
    }
    // Silently ensure settings is an array.
    if (!is_array($element['#settings'])) {
      $element['#settings'] = [];
    }

    $extractor_data = $icon->getAllData();
    // Inject attributes variable if not created by the extractor.
    if (!isset($extractor_data['attributes'])) {
      $extractor_data['attributes'] = new Attribute();
    }

    $element['inline-template'] = [
      '#type' => 'inline_template',
      '#template' => $icon->getTemplate(),
      // Context include data from extractor and settings, priority on settings
      // from this element. Context as last value to be sure nothing override
      // icon_id or source if set.
      '#context' => array_merge($extractor_data, $element['#settings'], $context),
    ];

    if ($library = $icon->getLibrary()) {
      $element['inline-template']['#attached'] = ['library' => [$library]];
    }

    return $element;
  }

  /**
   * Create an icon element.
   *
   * @param mixed $pre_render
   *   Automatically converted param.
   * @param mixed $pack_id
   *   Automatically converted param.
   * @param mixed $icon_id
   *   Automatically converted param.
   * @param mixed $settings
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
  public static function createInstance($pre_render, $pack_id, $icon_id, $settings, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) : static {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('icon');
    $instance->pre_render = $pre_render;
    $instance->pack_id = $pack_id;
    $instance->icon_id = $icon_id;
    $instance->settings = $settings;
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
      '#pack_id' => $this->pack_id,
      '#icon_id' => $this->icon_id,
      '#settings' => $this->settings,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
