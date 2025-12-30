<?php

namespace Drupal\views\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Plugin attribute object for views wizard plugins.
 *
 * @see \Drupal\views\Plugin\views\wizard\WizardPluginBase
 * @see \Drupal\views\Plugin\views\wizard\WizardInterface
 *
 * @ingroup views_wizard_plugins
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ViewsWizard extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $title,
    public readonly ?string $base_table = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
