<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the MenuTreeColumn plugin attribute.
 *
 * Modules add columns to the admin tree by placing a class that implements
 * MenuTreeColumnInterface in src/Plugin/MenuTreeColumn/ and decorating it
 * with this attribute.
 *
 * @code
 * #[MenuTreeColumn(
 *   id: 'my_column',
 *   title: new TranslatableMarkup('My column'),
 *   weight: 20,
 * )]
 * @endcode
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class MenuTreeColumn extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $title,
    public readonly int $weight = 0,
    public readonly ?string $deriver = NULL,
  ) {}

}
