<?php

namespace Drupal\Core\Extension;

/**
 * Manages the list of available themes.
 */
interface ThemeHandlerInterface {

  /**
   * Returns a list of currently installed themes.
   *
   * @return \Drupal\Core\Extension\Theme[]
   *   An associative array of the currently installed themes. The keys are the
   *   themes' machine names and the values are Extension objects having the
   *   following properties:
   *   - type: The type of the extension (theme).
   *   - pathname: The path to the theme directory.
   *   - filename: The filepath and name of the .info.yml file.
   *   - splFileInfo: Information about the file, if available.
   *   - root: The root path of the extension.
   *   - info: The contents of the .info.yml file.
   *   - subpath: The subpath, if any, within the theme directory where the
   *     theme resides. This is useful for multi-theme configurations where
   *     themes may reside in subdirectories within the main theme directory.
   *   - origin: The origin of the extension, if available.
   *   - owner: The extension that owns this extension, if any.
   *   - prefix: The base theme engine prefix.
   *   - status: 1 for installed, 0 for uninstalled themes.
   *   - required_by: An array of extensions that require this extension.
   *   - requires: An array of extensions that this extension requires.
   *   - sort: The sort order of this extension.
   *   - module_dependencies: An associative array of module dependencies for
   *     the extension.
   */
  public function listInfo();

  /**
   * Adds a theme extension to the internal listing.
   *
   * @param \Drupal\Core\Extension\Theme $theme
   *   The theme extension.
   */
  public function addTheme(Theme $theme);

  /**
   * Refreshes the theme info data of currently installed themes.
   *
   * Modules can alter theme info, so this is typically called after a module
   * has been installed or uninstalled.
   */
  public function refreshInfo();

  /**
   * Resets the internal state of the theme handler.
   */
  public function reset();

  /**
   * Gets the human readable name of a given theme.
   *
   * @param string $theme
   *   The machine name of the theme which title should be shown.
   *
   * @return string
   *   Returns the human readable name of the theme.
   *
   * @throws \Drupal\Core\Extension\Exception\UnknownExtensionException
   *   When the specified theme does not exist.
   */
  public function getName($theme);

  /**
   * Returns the default theme.
   *
   * @return string
   *   The default theme.
   */
  public function getDefault();

  /**
   * Returns an array of directories for all installed themes.
   *
   * Useful for tasks such as finding a file that exists in all theme
   * directories.
   *
   * @return array
   *   An associative array containing the directory path for all installed
   *   themes. The array is keyed by the theme name.
   */
  public function getThemeDirectories();

  /**
   * Determines whether a given theme is installed.
   *
   * @param string $theme
   *   The machine name of the theme.
   *
   * @return bool
   *   TRUE if the theme is installed.
   */
  public function themeExists($theme);

  /**
   * Returns a theme extension object from the currently active theme list.
   *
   * @param string $name
   *   The name of the theme to return.
   *
   * @return \Drupal\Core\Extension\Theme
   *   A theme extension object.
   *
   * @throws \Drupal\Core\Extension\Exception\UnknownExtensionException
   *   Thrown when the requested theme does not exist.
   */
  public function getTheme($name);

  /**
   * Determines if a theme should be shown in the user interface.
   *
   * To be shown in the UI the theme has to be installed. If the theme is hidden
   * it will not be shown unless it is the default or admin theme.
   *
   * @param string $name
   *   The name of the theme to check.
   *
   * @return bool
   *   TRUE if the theme should be shown in the UI, FALSE if not.
   */
  public function hasUi($name);

}
