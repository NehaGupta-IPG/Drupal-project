<?php

declare(strict_types=1);

namespace Drupal\navigation\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\navigation\TopBarRegion;

/**
 * The top bar item attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class TopBarItem extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TopBarRegion $region,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?string $deriver = NULL,
    public readonly int $weight = 0,
  ) {}

}
