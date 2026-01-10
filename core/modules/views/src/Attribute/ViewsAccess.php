<?php

namespace Drupal\views\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a views access plugins type attribute for plugin discovery.
 *
 * @see \Drupal\views\Plugin\views\access\AccessPluginBase
 *
 * @ingroup views_access_plugins
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ViewsAccess extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $title,
    public readonly ?TranslatableMarkup $short_title = NULL,
    public readonly ?TranslatableMarkup $help = NULL,
    public readonly ?array $display_types = NULL,
    public readonly array $base = [],
    public readonly bool $no_ui = FALSE,
    public readonly ?string $deriver = NULL,
  ) {}

}
