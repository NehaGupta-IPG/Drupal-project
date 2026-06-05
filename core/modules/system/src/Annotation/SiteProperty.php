<?php

namespace Drupal\system\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines site_property annotation object.
 *
 * @Annotation
 */
class SiteProperty extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The property name to apply to the site.
   *
   * @var string
   */
  public $name;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
