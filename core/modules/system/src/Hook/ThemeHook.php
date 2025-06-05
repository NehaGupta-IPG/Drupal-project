<?php

namespace Drupal\system\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Theme\ThemeCommonElements;

/**
 * Hook implementations for system.
 */
class ThemeHook {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    $themeCommonElements = ThemeCommonElements::commonElements();

    $systemTheme = [
      // Normally theme suggestion templates are only picked up when they are in
      // themes. We explicitly define theme suggestions here so that the block
      // templates in core/modules/system/templates are picked up.
      'block' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessBlock',
      ],
      'block__system_branding_block' => [
        'render element' => 'elements',
        'base hook' => 'block',
      ],
      'block__system_messages_block' => [
        'base hook' => 'block',
      ],
      'block__system_menu_block' => [
        'render element' => 'elements',
        'base hook' => 'block',
      ],
      'system_themes_page' => [
        'variables' => [
          'theme_groups' => [],
          'theme_group_titles' => [],
        ],
        'file' => 'system.admin.inc',
      ],
      'system_config_form' => [
        'render element' => 'form',
      ],
      'confirm_form' => [
        'render element' => 'form',
      ],
      'system_modules_details' => [
        'render element' => 'form',
        'file' => 'system.admin.inc',
      ],
      'system_modules_uninstall' => [
        'render element' => 'form',
        'file' => 'system.admin.inc',
      ],
      'status_report_page' => [
        'variables' => [
          'counters' => [],
          'general_info' => [],
          'requirements' => NULL,
        ],
      ],
      'status_report' => [
        'variables' => [
          'grouped_requirements' => NULL,
          'requirements' => NULL,
        ],
      ],
      'status_report_counter' => [
        'variables' => [
          'amount' => NULL,
          'text' => NULL,
          'severity' => NULL,
        ],
      ],
      'status_report_general_info' => [
        'variables' => [
          'drupal' => [],
          'cron' => [],
          'database_system' => [],
          'database_system_version' => [],
          'php' => [],
          'php_memory_limit' => [],
          'webserver' => [],
        ],
      ],
      'admin_page' => [
        'variables' => [
          'blocks' => NULL,
        ],
        'file' => 'system.admin.inc',
      ],
      'admin_block' => [
        'variables' => [
          'block' => NULL,
          'attributes' => [],
        ],
        'file' => 'system.admin.inc',
      ],
      'admin_block_content' => [
        'variables' => [
          'content' => NULL,
        ],
        'file' => 'system.admin.inc',
      ],
      'system_admin_index' => [
        'variables' => [
          'menu_items' => NULL,
        ],
        'file' => 'system.admin.inc',
      ],
      'entity_add_list' => [
        'variables' => [
          'bundles' => [],
          'add_bundle_message' => NULL,
        ],
        'template' => 'entity-add-list',
      ],
      'system_security_advisories_fetch_error_message' => [
        'file' => 'system.theme.inc',
        'variables' => [
          'error_message' => [],
        ],
      ],
      'entity_page_title' => [
        'variables' => [
          'attributes' => [],
          'title' => NULL,
          'entity' => NULL,
          'view_mode' => NULL,
        ],
      ],
    ];

    return array_merge($themeCommonElements, $systemTheme);
  }

  /**
   * Prepares variables for block templates.
   *
   * Default template: block.html.twig.
   *
   * Prepares the values passed to the theme_block function to be passed
   * into a pluggable template engine. Uses block properties to generate a
   * series of template file suggestions. If none are found, the default
   * block.html.twig is used.
   *
   * Most themes use their own copy of block.html.twig. The default is located
   * inside "core/modules/block/templates/block.html.twig". Look in there for
   * the full list of available variables.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An associative array containing the properties of the
   *     element. Properties used: #block, #configuration, #children,
   *     and #plugin_id.
   */
  public function preprocessBlock(&$variables): void {
    $variables['configuration'] = $variables['elements']['#configuration'];
    $variables['plugin_id'] = $variables['elements']['#plugin_id'];
    $variables['base_plugin_id'] = $variables['elements']['#base_plugin_id'];
    $variables['derivative_plugin_id'] = $variables['elements']['#derivative_plugin_id'];
    $variables['in_preview'] = $variables['elements']['#in_preview'] ?? FALSE;
    $variables['label'] = !empty($variables['configuration']['label_display']) ? $variables['configuration']['label'] : '';
    $variables['content'] = $variables['elements']['content'];
    // A block's label is configuration: it is static. Allow dynamic labels to
    // be set in the render array.
    if (isset($variables['elements']['content']['#title']) && !empty($variables['configuration']['label_display'])) {
      $variables['label'] = $variables['elements']['content']['#title'];
    }

    // Create a valid HTML ID and make sure it is unique.
    if (!empty($variables['elements']['#id'])) {
      $variables['attributes']['id'] = Html::getUniqueId('block-' . $variables['elements']['#id']);
    }

    // Proactively add aria-describedby if possible to improve accessibility.
    if ($variables['label'] && isset($variables['attributes']['role'])) {
      $variables['title_attributes']['id'] = Html::getUniqueId($variables['label']);
      $variables['attributes']['aria-describedby'] = $variables['title_attributes']['id'];
    }
  }

}
