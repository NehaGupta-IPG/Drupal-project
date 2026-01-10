<?php

namespace Drupal\Core\Block\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The Block attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Block extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $admin_label = NULL,
    public readonly ?TranslatableMarkup $category = NULL,
    public readonly array $context_definitions = [],
    public readonly ?string $deriver = NULL,
    public readonly array $forms = [],
  ) {}

}
