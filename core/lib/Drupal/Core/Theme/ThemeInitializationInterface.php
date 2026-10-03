<?php

namespace Drupal\Core\Theme;

use Drupal\Core\Extension\Extension;

/**
 * Defines an interface which contain theme initialization logic.
 */
interface ThemeInitializationInterface {

  /**
   * Initializes a given theme.
   *
   * This loads the active theme, for example include its engine file.
   *
   * @param string $theme_name
   *   The machine name of the theme.
   *
   * @return \Drupal\Core\Theme\ActiveTheme
   *   An active theme object instance for the given theme.
   */
  public function initTheme($theme_name);

  /**
   * Builds an active theme object.
   *
   * @param string $theme_name
   *   The machine name of the theme.
   *
   * @return \Drupal\Core\Theme\ActiveTheme
   *   An active theme object instance for the given theme.
   *
   * @throws \Drupal\Core\Theme\MissingThemeDependencyException
   *   Thrown when base theme for installed theme is not installed.
   */
  public function getActiveThemeByName($theme_name);

  /**
   * Builds up the active theme object from extensions.
   *
   * @param \Drupal\Core\Extension\Extension $theme
   *   The theme extension object.
   * @param \Drupal\Core\Extension\Extension[] $base_themes
   *   An array of extension objects of base theme and its bases. It is ordered
   *   by 'next parent first', meaning the top level of the chain will be first.
   *
   * @return \Drupal\Core\Theme\ActiveTheme
   *   The active theme instance for the passed in $theme.
   */
  public function getActiveTheme(Extension $theme, array $base_themes = []);

}
