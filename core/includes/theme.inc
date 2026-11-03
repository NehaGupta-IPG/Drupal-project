<?php

/**
 * @file
 * The theme system, which controls the output of Drupal.
 *
 * The theme system allows for nearly all output of the Drupal system to be
 * customized by user themes.
 */

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Config\Config;
use Drupal\Core\Theme\TemplateFinder;
use Drupal\Core\Theme\ThemeSuggestions;

/**
 * @defgroup content_flags Content markers
 * @{
 * Markers used by mark.html.twig to designate content.
 *
 * @see mark.html.twig
 */

/**
 * Mark content as read.
 */
const MARK_READ = 0;

/**
 * Mark content as being new.
 */
const MARK_NEW = 1;

/**
 * Mark content as being updated.
 */
const MARK_UPDATED = 2;

/**
 * A responsive table class; hide table cell on narrow devices.
 *
 * Indicates that a column has medium priority and thus can be hidden on narrow
 * width devices and shown on medium+ width devices (i.e. tablets and desktops).
 */
const RESPONSIVE_PRIORITY_MEDIUM = 'priority-medium';

/**
 * A responsive table class; only show table cell on wide devices.
 *
 * Indicates that a column has low priority and thus can be hidden on narrow
 * and medium viewports and shown on wide devices (i.e. desktops).
 */
const RESPONSIVE_PRIORITY_LOW = 'priority-low';

/**
 * @} End of "defgroup content_flags".
 */

/**
 * Returns an array of default theme features.
 *
 * @deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use
 *   \Drupal\Core\Extension\ThemeSettingsProvider::DEFAULT_THEME_FEATURES
 *   instead.
 *
 * @see https://www.drupal.org/node/3554127
 */
function _system_default_theme_features() {
  @trigger_error('_system_default_theme_features() is deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use \Drupal\Core\Extension\ThemeSettingsProvider::DEFAULT_THEME_FEATURES instead. See https://www.drupal.org/node/3554127', E_USER_DEPRECATED);
  return ThemeSettingsProvider::DEFAULT_THEME_FEATURES;
}

/**
 * Allows themes and/or theme engines to easily discover overridden templates.
 *
 * @param array $cache
 *   The existing cache of theme hooks to test against.
 * @param string $extension
 *   The extension that these templates will have.
 * @param string $path
 *   The path to search.
 *
 * @deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Use
 *   \Drupal::service(TemplateFinder::class)->scan() instead.
 *
 * @see https://www.drupal.org/node/3632286
 */
function drupal_find_theme_templates($cache, $extension, $path): array {
  @trigger_error('drupal_find_theme_templates() is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Use \Drupal::service(TemplateFinder::class)->scan() instead. See https://www.drupal.org/node/3632286', E_USER_DEPRECATED);
  return \Drupal::service(TemplateFinder::class)->scan($cache, $extension, $path);
}

/**
 * Retrieves a setting for the current theme or for a given theme.
 *
 * The final setting is obtained from the last value found in the following
 * sources:
 * - the saved values from the global theme settings form
 * - the saved values from the theme's settings form
 * To only retrieve the default global theme setting, an empty string should be
 * given for $theme.
 *
 * @param string $setting_name
 *   The name of the setting to be retrieved.
 * @param string $theme
 *   The name of a given theme; defaults to the current theme.
 *
 * @return mixed
 *   The value of the requested setting, NULL if the setting does not exist.
 *
 * @deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use
 *   \Drupal::service('Drupal\Core\Extension\ThemeSettingsProvider')->getSetting()
 *   instead.
 *
 * @see https://www.drupal.org/node/3035289
 */
function theme_get_setting($setting_name, $theme = NULL) {
  @trigger_error('theme_get_setting() is deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use \Drupal::service(\'\Drupal\Core\Extension\ThemeSettingsProvider\')->getSetting() instead. See https://www.drupal.org/node/3035289', E_USER_DEPRECATED);
  return \Drupal::service(ThemeSettingsProvider::class)->getSetting($setting_name, $theme);
}

/**
 * Converts theme settings to configuration.
 *
 * @param array $theme_settings
 *   An array of theme settings from system setting form.
 * @param \Drupal\Core\Config\Config $config
 *   The configuration object to update.
 *
 * @return \Drupal\Core\Config\Config
 *   The Config object with updated data.
 *
 * @see system_theme_settings_submit()
 *
 * @deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. There
 *   is no replacement.
 *
 * @see https://www.drupal.org/node/3632286
 */
function theme_settings_convert_to_config(array $theme_settings, Config $config) {
  @trigger_error('theme_settings_convert_to_config() is deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. There is no replacement. See https://www.drupal.org/node/3632286', E_USER_DEPRECATED);
  foreach ($theme_settings as $key => $value) {
    if ($key == 'default_logo') {
      $config->set('logo.use_default', $value);
    }
    elseif ($key == 'logo_path') {
      $config->set('logo.path', $value);
    }
    elseif ($key == 'default_favicon') {
      $config->set('favicon.use_default', $value);
    }
    elseif ($key == 'favicon_path') {
      $config->set('favicon.path', $value);
    }
    elseif ($key == 'favicon_mimetype') {
      $config->set('favicon.mimetype', $value);
    }
    elseif (str_starts_with($key, 'toggle_')) {
      $config->set('features.' . mb_substr($key, 7), $value);
    }
    elseif (!in_array($key, ['theme', 'logo_upload'])) {
      $config->set($key, $value);
    }
  }
  return $config;
}

/**
 * Generate an array of suggestions from path arguments.
 *
 * This is typically called for adding to the suggestions in
 * hook_theme_suggestions_HOOK_alter() or adding to 'attributes' class key
 * variables from within preprocess functions, when wanting to base the
 * additional suggestions or classes on the path of the current page.
 *
 * @param array $args
 *   An array of path arguments.
 * @param string $base
 *   A string identifying the base 'thing' from which more specific suggestions
 *   are derived. For example, 'page' or 'html'.
 * @param string $delimiter
 *   The string used to delimit increasingly specific information. The default
 *   of '__' is appropriate for theme hook suggestions. '-' is appropriate for
 *   extra classes.
 *
 * @return array
 *   An array of suggestions, suitable for adding to
 *   hook_theme_suggestions_HOOK_alter() or to $variables['attributes']['class']
 *   if the suggestions represent extra CSS classes.
 *
 * @deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Use
 *   \Drupal::service(ThemeSuggestions::class)->get() instead.
 *
 * @see https://www.drupal.org/node/3632286
 */
function theme_get_suggestions($args, $base, $delimiter = '__'): array {
  @trigger_error('theme_get_suggestions() is deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use \Drupal::service(ThemeSuggestions::class)->get() instead. See https://www.drupal.org/node/3632286', E_USER_DEPRECATED);
  return \Drupal::service(ThemeSuggestions::class)->get($args, $base, $delimiter);
}
