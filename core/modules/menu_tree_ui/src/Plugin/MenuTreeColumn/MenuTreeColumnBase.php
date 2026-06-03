<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Plugin\MenuTreeColumn;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Base class for MenuTreeColumn plugins.
 *
 * Concrete columns must implement getCellData(). handleUpdate() defaults to
 * FALSE (read-only) — override it for interactive columns.
 *
 * @api
 */
abstract class MenuTreeColumnBase extends PluginBase implements MenuTreeColumnInterface {

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
  abstract public function getCellData(MenuLinkTreeElement $element): array;

  /**
   * {@inheritdoc}
   *
   * Read-only by default. Interactive columns must override this.
   */
  public function handleUpdate(EntityInterface $entity, mixed $value): bool {
    return FALSE;
  }

}
