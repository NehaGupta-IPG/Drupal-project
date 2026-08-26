<?php

declare(strict_types=1);

namespace Drupal\Core\Theme;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Determines where the user color scheme preference has an effect.
 *
 * A theme honors the preference by declaring 'supports color scheme: true' in
 * its .info.yml file. The declaration is inherited from base themes.
 *
 * Two questions follow from that declaration, and both are answered here so
 * that the account form, the html preprocess and the page attachments hook
 * agree:
 * - Which of the configured themes declare support. The account form describes
 *   the effect of the preference from that.
 * - Whether the preference has an effect on the page being rendered for a
 *   given account. The data-color-scheme attribute on the <html> element and
 *   the 'user.color_scheme' cache context follow from that.
 *
 * @see \Drupal\Core\Theme\ActiveTheme::supportsColorScheme()
 * @see \Drupal\Core\Theme\ThemePreprocess::preprocessHtml()
 * @see \Drupal\system\Hook\PageAttachmentsHook::pageAttachments()
 * @see \Drupal\user\AccountForm::colorSchemeElementProperties()
 */
class ColorSchemeSupport {

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ThemeInitializationInterface $themeInitialization,
    protected readonly ThemeManagerInterface $themeManager,
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
   * Determines whether the preference has an effect for an account.
   *
   * The active theme renders the page, so its declaration settles the question
   * on its own. A page of a theme without support still carries output of the
   * administration theme, such as the navigation and its top bar, and only
   * accounts that may use the administration theme ever see that output.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account the page is rendered for.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface|null $cacheability
   *   (optional) Collects the cacheability of the decision.
   *
   * @return bool
   *   TRUE if the color scheme of the account has an effect on the page.
   *
   * @see \Drupal\user\Theme\AdminNegotiator::applies()
   */
  public function appliesToAccount(AccountInterface $account, ?RefinableCacheableDependencyInterface $cacheability = NULL): bool {
    if ($this->themeManager->getActiveTheme()->supportsColorScheme()) {
      return TRUE;
    }
    // The theme configuration is read on every page, so it comes first. The
    // permission check needs the user module, and a page whose administration
    // theme cannot honor the preference must not reach it.
    if (!$this->adminThemeSupports($cacheability)) {
      return FALSE;
    }
    // The permission decides the outcome from here on, so the page varies by
    // it.
    $cacheability?->addCacheContexts(['user.permissions']);
    return $account->hasPermission('view the administration theme');
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
