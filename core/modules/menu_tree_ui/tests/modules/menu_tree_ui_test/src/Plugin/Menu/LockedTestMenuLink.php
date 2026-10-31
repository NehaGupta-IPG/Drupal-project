<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui_test\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkBase;

/**
 * A menu link whose parent and weight cannot be changed.
 *
 * It extends MenuLinkBase rather than MenuLinkDefault, because the tree UI
 * treats only content links and MenuLinkDefault links as movable.
 */
final class LockedTestMenuLink extends MenuLinkBase {

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    return (string) $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) ($this->pluginDefinition['description'] ?? '');
  }

  /**
   * {@inheritdoc}
   */
  public function updateLink(array $new_definition_values, $persist): array {
    return $this->pluginDefinition;
  }

}
