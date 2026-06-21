<?php

declare(strict_types=1);

namespace Drupal\treegrid\Plugin\TreegridColumn;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Base class for TreegridColumn plugins.
 *
 * Concrete columns must implement getCellData(). handleUpdate() defaults to
 * FALSE (read-only) — override it for interactive columns.
 *
 * @api
 */
abstract class TreegridColumnBase extends PluginBase implements TreegridColumnInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getHeader(): TranslatableMarkup {
    return $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  abstract public function getCellData(object $context): array;

  /**
   * {@inheritdoc}
   *
   * Read-only by default. Interactive columns must override this.
   */
  public function handleUpdate(EntityInterface $entity, mixed $value): bool {
    return FALSE;
  }

}
