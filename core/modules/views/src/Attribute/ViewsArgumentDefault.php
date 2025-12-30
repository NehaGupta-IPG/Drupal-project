<?php

declare(strict_types=1);

namespace Drupal\views\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ViewsArgument attribute for plugin discovery.
 *
 * @see \Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase
 *
 * @ingroup views_argument_default_plugins
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ViewsArgumentDefault extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $title = NULL,
    public readonly ?TranslatableMarkup $short_title = NULL,
    public readonly bool $no_ui = FALSE,
    public readonly ?string $deriver = NULL,
  ) {}

}
