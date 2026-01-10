<?php

namespace Drupal\Core\Entity\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an EntityReferenceSelection attribute for plugin discovery.
 *
 * Plugin Namespace: Plugin\EntityReferenceSelection
 *
 * For a working example, see
 * \Drupal\comment\Plugin\EntityReferenceSelection\CommentSelection
 *
 * @see \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManager
 * @see \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class EntityReferenceSelection extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly string $group,
    public readonly int $weight,
    public readonly array $entity_types = [],
    public readonly ?string $deriver = NULL,
  ) {}

}
