<?php

declare(strict_types=1);

namespace Drupal\views\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Plugin attribute object for views display plugins.
 *
 * @see \Drupal\views\Plugin\views\display\DisplayPluginBase
 *
 * @ingroup views_display_plugins
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ViewsDisplay extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $title,
    public readonly ?TranslatableMarkup $short_title = NULL,
    public readonly ?TranslatableMarkup $admin = NULL,
    public readonly ?TranslatableMarkup $help = NULL,
    public readonly bool $uses_menu_links = FALSE,
    public readonly bool $uses_route = FALSE,
    public readonly bool $uses_hook_block = FALSE,
    public readonly bool $returns_response = FALSE,
    public readonly ?array $contextual_links_locations = NULL,
    public readonly array $base = [],
    public readonly ?string $theme = NULL,
    public readonly bool $no_ui = FALSE,
    public readonly bool $register_theme = TRUE,
    public readonly bool $entity_reference_display = FALSE,
    public readonly ?string $deriver = NULL,
  ) {}

}
