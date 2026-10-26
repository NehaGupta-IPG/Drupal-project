<?php

namespace Drupal\options\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a predefined options item annotation object.
 *
 * @see \Drupal\options\Plugin\PredefinedOptionsPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class PredefinedOptions extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
