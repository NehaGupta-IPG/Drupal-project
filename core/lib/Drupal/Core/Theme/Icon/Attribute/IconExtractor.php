<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Icon\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The icon_extractor attribute.
 *
 * @internal
 *   This API is experimental.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class IconExtractor extends AttributeBase {

  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $deriver = NULL,
    public readonly array $forms = [],
  ) {}

}
