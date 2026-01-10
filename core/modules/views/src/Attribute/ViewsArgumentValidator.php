<?php

namespace Drupal\views\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ViewsArgumentValidator attribute object for plugin discovery.
 *
 * Plugin Namespace: Plugin\ViewsArgumentValidator
 *
 * @see \Drupal\views\Plugin\views\argument_validator\ArgumentValidatorPluginBase
 *
 * @ingroup views_argument_validate_plugins
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ViewsArgumentValidator extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $title = NULL,
    public readonly ?TranslatableMarkup $short_title = NULL,
    public readonly ?string $entity_type = NULL,
    public readonly bool $no_ui = FALSE,
    public readonly ?string $deriver = NULL,
  ) {}

}
