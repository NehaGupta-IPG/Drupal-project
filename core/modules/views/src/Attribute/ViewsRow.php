<?php

declare(strict_types=1);

namespace Drupal\views\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ViewsRow attribute for plugin discovery.
 *
 * @see \Drupal\views\Plugin\views\style\StylePluginBase
 *
 * @ingroup views_row_plugins
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ViewsRow extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $title = NULL,
    public readonly ?TranslatableMarkup $short_title = NULL,
    public readonly ?TranslatableMarkup $help = NULL,
    public readonly array $display_types = [],
    public readonly array $base = [],
    public readonly ?string $theme = NULL,
    public readonly bool $no_ui = FALSE,
    public readonly bool $register_theme = TRUE,
    public readonly ?string $deriver = NULL,
  ) {}

}
