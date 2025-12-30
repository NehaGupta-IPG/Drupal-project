<?php

declare(strict_types=1);

namespace Drupal\views\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ViewsQuery attribute for plugin discovery.
 *
 * @see \Drupal\views\Plugin\views\query\QueryPluginBase
 *
 * @ingroup views_query_plugins
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ViewsQuery extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $title,
    public readonly ?TranslatableMarkup $short_title = NULL,
    public readonly ?TranslatableMarkup $help = NULL,
    public readonly bool $no_ui = FALSE,
    public readonly ?string $deriver = NULL,
  ) {}

}
