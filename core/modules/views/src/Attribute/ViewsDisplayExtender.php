<?php

namespace Drupal\views\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Plugin attribute object for views display extender plugins.
 *
 * @see \Drupal\views\Plugin\views\display_extender\DisplayExtenderPluginBase
 *
 * @ingroup views_display_extender_plugins
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ViewsDisplayExtender extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $title = NULL,
    public readonly ?TranslatableMarkup $short_title = NULL,
    public readonly ?TranslatableMarkup $help = NULL,
    public readonly bool $no_ui = FALSE,
    public readonly ?string $deriver = NULL,
  ) {}

}
