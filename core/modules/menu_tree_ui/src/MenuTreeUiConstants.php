<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui;

/**
 * Shared constants for the menu_tree_ui module.
 */
final class MenuTreeUiConstants {

  /**
   * Plugin ID prefix that identifies a menu_link_content entity link.
   *
   * Plugin IDs look like "menu_link_content:{uuid}". Anything else (e.g.
   * "system.admin_content") is a static yml-defined link.
   */
  const string CONTENT_PLUGIN_PREFIX = 'menu_link_content:';

  /**
   * Whether a plugin ID belongs to a menu_link_content entity link.
   *
   * @param string $plugin_id
   *   The menu link plugin ID.
   *
   * @return bool
   *   TRUE for "menu_link_content:{uuid}" IDs, FALSE for static links.
   */
  public static function isContentLink(string $plugin_id): bool {
    return str_starts_with($plugin_id, self::CONTENT_PLUGIN_PREFIX);
  }

  /**
   * Extracts the entity UUID from a content link's plugin ID.
   *
   * @param string $plugin_id
   *   The menu link plugin ID.
   *
   * @return string|null
   *   The UUID, or NULL when the ID is not a content link.
   */
  public static function uuidFromPluginId(string $plugin_id): ?string {
    return self::isContentLink($plugin_id)
      ? substr($plugin_id, strlen(self::CONTENT_PLUGIN_PREFIX))
      : NULL;
  }

}
