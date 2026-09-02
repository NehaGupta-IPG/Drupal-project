<?php

declare(strict_types=1);

namespace Drupal\Core\Theme;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Determines which of the configured themes honor the color scheme preference.
 *
 * A theme honors the preference by declaring 'supports color scheme: true' in
 * its .info.yml file. The declaration is inherited from base themes.
 *
 * The rendered page does not depend on any of this: every page carries
 * data-color-scheme="auto" and a cookie tells the client what to override it
 * with. The account form is the only consumer, and it describes to the account
 * where the preference takes effect.
 *
 * @see \Drupal\Core\Theme\ActiveTheme::supportsColorScheme()
 * @see \Drupal\user\AccountForm::colorSchemeElementProperties()
 */
class ColorSchemeSupport {

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ThemeInitializationInterface $themeInitialization,
  ) {}

  /**
   * Determines whether the site theme honors the color scheme preference.
   *
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface|null $cacheability
   *   (optional) Collects the cacheability of the decision.
   *
   * @return bool
   *   TRUE if the configured site theme declares support.
   */
  public function siteThemeSupports(?RefinableCacheableDependencyInterface $cacheability = NULL): bool {
    $themes = $this->getConfiguredThemeNames($cacheability);
    return $this->themeSupports($themes['default']);
  }

  /**
   * Determines whether the administration theme honors the preference.
   *
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface|null $cacheability
   *   (optional) Collects the cacheability of the decision.
   *
   * @return bool
   *   TRUE if the configured administration theme declares support.
   */
  public function adminThemeSupports(?RefinableCacheableDependencyInterface $cacheability = NULL): bool {
    $themes = $this->getConfiguredThemeNames($cacheability);
    return $this->themeSupports($themes['admin']);
  }

  /**
   * Returns the configured site theme and administration theme names.
   *
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface|null $cacheability
   *   Collects the cacheability of reading the theme configuration.
   *
   * @return array{default: string, admin: string}
   *   The site theme name and the administration theme name. An empty 'admin'
   *   value means administration pages use the site theme, so the site theme
   *   name is returned for both.
   */
  protected function getConfiguredThemeNames(?RefinableCacheableDependencyInterface $cacheability): array {
    $config = $this->configFactory->get('system.theme');
    $cacheability?->addCacheableDependency($config);
    $default = (string) $config->get('default');
    return [
      'default' => $default,
      'admin' => ((string) $config->get('admin')) ?: $default,
    ];
  }

  /**
   * Determines whether a named theme honors the color scheme preference.
   *
   * @param string $theme_name
   *   The machine name of the theme, or an empty string if none is configured.
   *
   * @return bool
   *   TRUE if the theme, or one of its base themes, declares support.
   */
  protected function themeSupports(string $theme_name): bool {
    if ($theme_name === '') {
      return FALSE;
    }
    try {
      // For a theme that is not installed, getActiveThemeByName() returns the
      // default output of core, which declares no support.
      return $this->themeInitialization->getActiveThemeByName($theme_name)->supportsColorScheme();
    }
    catch (MissingThemeDependencyException) {
      // The theme is installed but a base theme of it is not. Broken theme
      // configuration must not break the caller.
      return FALSE;
    }
  }

}
