<?php

declare(strict_types=1);

namespace Drupal\treegrid\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the TreegridColumn plugin attribute.
 *
 * Modules add columns to a treegrid admin UI by placing a class that implements
 * TreegridColumnInterface in src/Plugin/TreegridColumn/ and decorating it with
 * this attribute.
 *
 * @code
 * #[TreegridColumn(
 *   id: 'my_column',
 *   title: new TranslatableMarkup('My column'),
 *   weight: 20,
 * )]
 * @endcode
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class TreegridColumn extends Plugin {

  /**
   * Constructs the attribute.
   *
   * @param string $id
   *   The column plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The column header label.
   * @param int $weight
   *   Sort order: columns render left to right by ascending weight.
   * @param string|null $deriver
   *   Optional deriver class that expands this definition into multiple
   *   column definitions.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $title,
    public readonly int $weight = 0,
    public readonly ?string $deriver = NULL,
  ) {}

}
