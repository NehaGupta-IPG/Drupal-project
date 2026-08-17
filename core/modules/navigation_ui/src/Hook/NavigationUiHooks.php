<?php

namespace Drupal\navigation_ui\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\RemoveHook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\Hook\LayoutBuilderHooks;
use Drupal\navigation_ui\Plugin\SectionStorage\NavigationSectionStorage;
use Drupal\navigation_ui\RenderCallbacks;

/**
 * Hook implementations for navigation_ui.
 */
class NavigationUiHooks {

  use StringTranslationTrait;

  /**
   * NavigationUiHooks constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(protected ModuleHandlerInterface $moduleHandler) {
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  #[RemoveHook('help', class: LayoutBuilderHooks::class, method: 'help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.navigation_ui':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('The Navigation UI module provides a Layout Builder interface for configuring navigation blocks.') . '</p>';
        $output .= '<p>' . $this->t('For more information, see the <a href=":docs">online documentation for the Navigation UI module</a>.', [':docs' => 'https://www.drupal.org/docs/develop/core-modules-and-themes/core-modules/navigation-module']) . '</p>';
        return $output;
    }
    $configuration_route = 'layout_builder.navigation.';
    if (!$route_match->getRouteObject()->getOption('_layout_builder') || !str_starts_with($route_name, $configuration_route)) {
      return $this->moduleHandler->invoke('layout_builder', 'help', [$route_name, $route_match]);
    }
    if (str_starts_with($route_name, $configuration_route)) {
      $output = '<p>' . $this->t('This layout builder tool allows you to configure the blocks in the navigation toolbar.') . '</p>';
      $output .= '<p>' . $this->t('Forms and links inside the content of the layout builder tool are disabled in Edit mode.') . '</p>';
      return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_plugin_filter_TYPE__CONSUMER_alter().
   *
   * Curate the blocks available in the Layout Builder "Add Block" UI.
   */
  #[Hook('plugin_filter_block__layout_builder_alter')]
  public function pluginFilterBlockLayoutBuilderAlter(array &$definitions, array $extra): void {
    if (($extra['section_storage'] ?? NULL) instanceof NavigationSectionStorage) {
      // Include only blocks explicitly indicated as Navigation allowed.
      $definitions = array_filter($definitions,
        fn (array $definition): bool => ($definition['allow_in_navigation'] ?? FALSE) === TRUE
      );
    }
  }

  /**
   * Implements hook_plugin_filter_TYPE__CONSUMER_alter().
   */
  #[Hook('plugin_filter_layout__layout_builder_alter')]
  public function pluginFilterLayoutLayoutBuilderAlter(array &$definitions, array $extra): void {
    if (($extra['section_storage'] ?? NULL) instanceof NavigationSectionStorage) {
      // We don't allow adding a new section.
      $definitions = [];
    }
  }

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$info): void {
    if (array_key_exists('layout_builder', $info)) {
      $info['layout_builder']['#pre_render'][] = [RenderCallbacks::class, 'alterLayoutBuilder'];
    }
  }

}
