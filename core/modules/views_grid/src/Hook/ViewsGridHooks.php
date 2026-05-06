<?php

namespace Drupal\views_grid\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Hook implementations for views_grid.
 */
class ViewsGridHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.views_grid':
        $output = '<h3>' . t('About') . '</h3>';
        $output .= '<p>' . t('The Views Grid (deprecated) module provides the deprecated grid style plugin for Views. This module is deprecated and will be removed in a future version of Drupal.') . '</p>';
        $output .= '<h3>' . t('Deprecation Notice') . '</h3>';
        $output .= '<p>' . t('This module is deprecated as of Drupal 12.0.0 and will be removed in Drupal 13.0.0. Use the <strong>Responsive Grid</strong> style plugin instead, which provides better mobile support and modern CSS Grid layout.') . '</p>';
        $output .= '<p>' . t('For more information, see the <a href=":url">change record</a>.', [':url' => 'https://www.drupal.org/node/3301811']) . '</p>';
        $output .= '<h3>' . t('Migration') . '</h3>';
        $output .= '<p>' . t('To migrate existing views:') . '</p>';
        $output .= '<ol>';
        $output .= '<li>' . t('Edit your view configuration') . '</li>';
        $output .= '<li>' . t('Change the format from "Grid (deprecated)" to "Responsive Grid"') . '</li>';
        $output .= '<li>' . t('Configure the responsive grid settings as needed') . '</li>';
        $output .= '<li>' . t('Uninstall the Views Grid module when no views use it anymore') . '</li>';
        $output .= '</ol>';
        $output .= '<h3>' . t('Documentation') . '</h3>';
        $output .= '<p>' . t('For more information, see the <a href=":url">online documentation for the Views Grid (deprecated) module</a>.', [':url' => 'https://www.drupal.org/docs/core-modules-and-themes/core-modules/views-module']) . '</p>';
        return $output;
    }
  }

}