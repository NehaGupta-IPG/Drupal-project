<?php

declare(strict_types=1);

namespace Drupal\filter\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a filter attribute for plugin discovery.
 *
 * Plugin Namespace: Plugin\Filter
 *
 * For a working example, see \Drupal\filter\Plugin\Filter\FilterHtml
 *
 * @see \Drupal\filter\FilterPluginManager
 * @see \Drupal\filter\Plugin\FilterInterface
 * @see \Drupal\filter\Plugin\FilterBase
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Filter extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $title,
    public readonly int $type,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly int $weight = 0,
    public readonly bool $status = FALSE,
    public readonly array $settings = [],
  ) {}

}
