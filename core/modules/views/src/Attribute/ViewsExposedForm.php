<?php

declare(strict_types=1);

namespace Drupal\views\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Plugin attribute object for views exposed form plugins.
 *
 * @see \Drupal\views\Plugin\views\exposed_form\ExposedFormPluginInterface
 * @see \Drupal\views\Plugin\views\exposed_form\ExposedFormPluginBase
 *
 * @ingroup views_exposed_form_plugins
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ViewsExposedForm extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $title,
    public readonly ?TranslatableMarkup $short_title = NULL,
    public readonly ?TranslatableMarkup $help = NULL,
    public readonly ?array $display_types = NULL,
    public readonly array $base = [],
    public readonly bool $no_ui = FALSE,
    public readonly bool $register_theme = TRUE,
    public readonly ?string $deriver = NULL,
  ) {}

}
