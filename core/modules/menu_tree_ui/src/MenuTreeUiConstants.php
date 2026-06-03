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

}
